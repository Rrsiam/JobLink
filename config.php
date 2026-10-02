<?php
// Database settings (replace with your own)
define('DB_HOST', 'localhost');
define('DB_NAME', 'joblink');
define('DB_USER', 'root');
define('DB_PASS', '');

// Timezone. Every created_at column is a TIMESTAMP with a CURRENT_TIMESTAMP
// default, so MySQL writes those values using the session timezone, while the
// pages read them back and format them with PHP. Leaving PHP to inherit whatever
// php.ini happens to say put the two clocks hours apart (PHP Europe/Berlin vs
// MySQL Asia/Dhaka on this box), which made fresh applications render as being
// in the future and "x ago" come out negative. Pinning both sides to Dhaka makes
// the stored instant and the rendered string agree, and stops the output from
// depending on the server's php.ini. getDB() sets the matching session tz.
date_default_timezone_set('Asia/Dhaka');

// Error display. Set JOBLINK_DEBUG=1 in the environment to show PHP errors in the
// browser; the default is off so a deployed site never leaks file paths, SQL or
// stack details to visitors. Errors are always logged regardless.
$joblinkDebug = getenv('JOBLINK_DEBUG');
define('APP_DEBUG', $joblinkDebug === '1' || $joblinkDebug === 'true');
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('display_startup_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

// Base URL – auto-detect from current request
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
define('BASE_URL', $scriptDir . '/');

// PDO statement wrapper so that ->execute(...)->fetch() chaining works
// (plain PDOStatement::execute() returns bool, not the statement)
class AppPDOStatement {
    private $stmt;

    public function __construct(PDOStatement $stmt) {
        $this->stmt = $stmt;
    }

    public function __call($method, $args) {
        return call_user_func_array([$this->stmt, $method], $args);
    }

    public function execute($params = null) {
        $this->stmt->execute($params);
        return $this;
    }

    public function fetch($mode = null) {
        return $mode === null ? $this->stmt->fetch() : $this->stmt->fetch($mode);
    }

    public function fetchAll($mode = null) {
        return $mode === null ? $this->stmt->fetchAll() : $this->stmt->fetchAll($mode);
    }

    public function fetchColumn($column = 0) {
        return $this->stmt->fetchColumn($column);
    }
}

class AppPDO extends PDO {
    #[\ReturnTypeWillChange]
    public function prepare($query, $options = []) {
        return new AppPDOStatement(parent::prepare($query, $options));
    }
}

// Database connection (PDO singleton)
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new AppPDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        // Match date_default_timezone_set() above. Without this the session
        // inherits the server's zone and CURRENT_TIMESTAMP defaults are written
        // in a different zone to the one PHP formats them in.
        $pdo->exec("SET time_zone = '+06:00'");
    }
    return $pdo;
}

// Wrapper marking a value as pre-built HTML that the caller has already
// escaped, so render() inserts it verbatim instead of escaping it again.
class RawHtml {
    public $html;
    public function __construct($html) {
        $this->html = (string)$html;
    }
}

// Escape a value for output in an HTML text or attribute context.
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES);
}

// Render engine: loads HTML template and replaces {{key}} with $data['key'].
// Wrap a value in RawHtml to emit markup verbatim; plain scalars are escaped.
function render($template, $data = []) {
    $path = __DIR__ . '/templates/' . $template;
    if (!file_exists($path)) {
        die("Template not found: $template");
    }
    // Default the identity fields to the signed-in account so dashboards do not
    // have to hardcode a display name. An explicit value from the controller
    // still wins, so profile pages can show another user's details.
    if (is_logged_in() && $user = get_user_data()) {
        foreach (['name' => 'name', 'role' => 'role'] as $key => $column) {
            if (!array_key_exists($key, $data)) {
                $data[$key] = (string)$user[$column];
            }
        }
    }
    $html = file_get_contents($path);
    foreach ($data as $key => $value) {
        if ($value instanceof RawHtml) {
            $html = str_replace('{{' . $key . '}}', $value->html, $html);
            continue;
        }
        if ($value === null || !is_scalar($value)) {
            continue;
        }
        // Everything is escaped unless a controller explicitly opted out with
        // RawHtml. The previous behaviour - "if it contains a < then it must be
        // markup, pass it through" - meant any value carrying a stray angle
        // bracket skipped escaping entirely, so employer- and applicant-supplied
        // text (job titles, locations, names, emails) reached the page as live
        // markup and ran as script.
        $html = str_replace('{{' . $key . '}}', e((string)$value), $html);
    }
    echo $html;
}

// Sanitise a post-login redirect target. Only relative query strings are
// accepted, so this can never be turned into an open redirect.
function safe_next_url($next) {
    $next = (string)$next;
    if ($next === '' || $next[0] !== '?' || strpos($next, '://') !== false || strpos($next, "\r") !== false || strpos($next, "\n") !== false) {
        return '';
    }
    return $next;
}

// Session helpers
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function get_user_role() {
    return $_SESSION['role'] ?? 'guest';
}

function get_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function get_user_data() {
    $id = get_user_id();
    if (!$id) {
        return null;
    }
    static $user = null;
    if ($user === null) {
        $user = getDB()->prepare("SELECT * FROM users WHERE id = ?")->execute([$id])->fetch();
    }
    return $user;
}

function require_role($role) {
    if (!is_logged_in()) {
        header('Location: ?page=sign-in');
        exit;
    }
    if (get_user_role() !== $role) {
        header('Location: ?role=' . get_user_role() . '&page=dashboard');
        exit;
    }
}

function get_categories() {
    return getDB()->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name")->fetchAll();
}

// Check whether an applicant's profile is complete enough to apply for jobs.
// Returns array of missing field labels (empty array = complete).
function applicant_profile_missing() {
    $user_id = get_user_id();
    if (!$user_id) {
        return ['Profile'];
    }
    $db = getDB();
    $user = get_user_data();
    $profile = $db->prepare("SELECT * FROM applicant_profiles WHERE user_id = ?")->execute([$user_id])->fetch();
    $profile = $profile ?: [];

    $checks = [
        'Phone number'               => !empty($user['phone']),
        'Professional title'         => !empty($profile['professional_title']),
        'Location / address'         => !empty($profile['address']),
        'Career objective'           => !empty($profile['career_objective']),
        'Education'                  => !empty($profile['education']),
        'Work experience'            => !empty($profile['experience']),
        'Skills'                     => !empty($profile['skills']),
        // Employers download the resume straight from the application, so an
        // application without one is useless to them. resume_resolve_path()
        // checks the file is really on disk, not just that a name is stored.
        'Resume upload'              => resume_resolve_path($profile['resume'] ?? '') !== '',
    ];

    $missing = [];
    foreach ($checks as $label => $ok) {
        if (!$ok) {
            $missing[] = $label;
        }
    }
    return $missing;
}

// Read one entry out of a ZIP archive using local file headers. Used to pull
// word/document.xml out of an uploaded .docx, because ZipArchive is not
// available in this XAMPP build. Returns the raw (still compressed) bytes of
// the entry, or '' when the entry is absent.
function zip_read_entry($data, $name_wanted) {
    $len = strlen($data);
    $offset = 0;

    while ($offset + 30 <= $len) {
        if (substr($data, $offset, 4) !== "PK\x03\x04") {
            break;
        }
        $head = unpack(
            'vver/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vsize/vnamelen/vextralen',
            substr($data, $offset + 4, 26)
        );
        $name = substr($data, $offset + 30, $head['namelen']);
        $data_start = $offset + 30 + $head['namelen'] + $head['extralen'];

        if ($name === $name_wanted) {
            $raw = substr($data, $data_start, $head['csize']);

            // Streamed entries store 0 in the local header, so fall back to
            // scanning for the next local file signature to find the end.
            if ($head['csize'] === 0) {
                $scan = 0;
                while ($scan + 4 <= strlen($raw) && substr($raw, $scan, 4) !== "PK\x03\x04") {
                    $scan++;
                }
                $raw = substr($raw, 0, $scan);
            }

            if ($head['method'] === 0) {
                return $raw;
            }
            if ($head['method'] === 8) {
                $inflated = @gzinflate($raw);
                return $inflated === false ? '' : $inflated;
            }
            return '';
        }

        $offset = $data_start + max($head['csize'], 1);
    }

    return '';
}

// Pull readable text out of an uploaded resume, whatever format it was saved
// in, so the whole resume can be laid out as a document inside the app instead
// of being handed straight to the browser's own file viewer.
// Returns plain (unescaped) text, or '' when nothing usable could be read.
function resume_extract_text($path) {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    if ($ext === 'pdf') {
        return resume_pdf_text($path);
    }

    if ($ext === 'docx') {
        $xml = zip_read_entry((string)@file_get_contents($path), 'word/document.xml');
        if ($xml === '') {
            return '';
        }
        // Preserve the layout cues that matter on a resume: tabs for column
        // alignment, newlines for paragraphs and line breaks.
        $xml = preg_replace('#<w:tab[^>]*/>#', "\t", $xml);
        $xml = preg_replace('#</w:p>#', "\n", $xml);
        $xml = preg_replace('#<w:br[^>]*/>#', "\n", $xml);
        $xml = preg_replace('#</w:tr>#', "\n", $xml);
        $xml = preg_replace('#</w:tc>#', "\t", $xml);
        $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
        return trim(preg_replace("/\n{3,}/", "\n\n", $text));
    }

    if ($ext === 'doc') {
        // Legacy .doc is an OLE compound file; pull the readable ASCII/UTF-8
        // runs out of the WordDocument stream without a full parser.
        $data = (string)@file_get_contents($path);
        if (preg_match_all('/[\x20-\x7E]{4,}/', $data, $m)) {
            return trim(implode("\n", $m[0]));
        }
        return '';
    }

    if ($ext === 'txt') {
        return (string)@file_get_contents($path);
    }

    return '';
}

// Decide whether extracted text is really a resume and not stray bytes that
// happened to look printable. Recovering text from a binary container is
// best-effort by nature, and a corrupt file can yield a handful of stray
// characters; laying those out as a CV would be worse than showing nothing.
function resume_text_is_readable($text) {
    $text = trim(preg_replace('/\s+/', ' ', (string)$text));
    if ($text === '') {
        return false;
    }

    // A resume header alone is several dozen characters. Anything shorter is a
    // fragment from a container's metadata, not content.
    if (mb_strlen($text) < 24) {
        return false;
    }

    // Binary noise leaves control characters and lone replacement bytes behind.
    $clean = preg_replace('/[\x20-\x7E\r\n\t]/', '', $text);
    if (strlen($clean) / max(1, strlen($text)) > 0.05) {
        return false;
    }

    // Real prose is made of words; a handful of fragments is not a CV.
    return preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\'&.\-]*/u', $text) >= 5;
}

// Read the text out of a PDF. Deliberately best-effort: it inflates the file's
// content streams and pulls the text-showing strings back out of them, which
// covers the PDFs the common word processors produce. Returns '' whenever the
// text cannot be recovered (encrypted files, subset fonts with no readable
// encoding, a scan of a printed resume) and the caller falls back to embedding
// the file itself.
function resume_pdf_text($path) {
    $data = (string)@file_get_contents($path);
    if ($data === '') {
        return '';
    }

    $text = '';
    if (preg_match_all('#<<(?:[^<>]|<<(?:[^<>]|<<[^>]*>>)*>>)*>>\s*stream\r?\n?(.*?)endstream#s', $data, $m)) {
        foreach ($m[1] as $chunk) {
            $body = @gzuncompress($chunk);
            if ($body === false) {
                $body = @gzinflate($chunk);
            }
            if ($body === false || $body === '') {
                $body = $chunk;
            }
            $text .= "\n" . resume_pdf_stream_text($body);
        }
    }

    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', ' ', $text);

    // A preview assembled from binary noise would be worse than no preview at
    // all, so anything that does not read like text is thrown away.
    $printable = strlen(preg_replace('/[^\x20-\x7E\r\n\t]/', '', $text));
    if ($printable < 200 || $printable < 0.8 * strlen($text)) {
        return '';
    }

    return trim($text);
}

// Walk one PDF content stream and return the text it shows. Literal and hex
// strings are decoded, and a newline is emitted at each text-positioning
// operator so the result keeps roughly the line structure of the document.
function resume_pdf_stream_text($body) {
    $text = '';
    $len = strlen($body);
    $i = 0;

    while ($i < $len) {
        $ch = $body[$i];

        if ($ch === '(') {
            $i++;
            $depth = 1;
            $buf = '';
            while ($i < $len) {
                $ch = $body[$i];
                if ($ch === '\\') {
                    $next = $i + 1 < $len ? $body[$i + 1] : '';
                    $map = ['n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\b", 'f' => "\f"];
                    $buf .= isset($map[$next]) ? $map[$next] : $next;
                    $i += 2;
                    continue;
                }
                if ($ch === '(') {
                    $depth++;
                } elseif ($ch === ')') {
                    $depth--;
                    if ($depth === 0) {
                        $i++;
                        break;
                    }
                }
                $buf .= $ch;
                $i++;
            }
            $text .= $buf;
            continue;
        }

        if ($ch === '<' && ($body[$i + 1] ?? '') !== '<') {
            $end = strpos($body, '>', $i);
            if ($end !== false) {
                $hex = preg_replace('/\s+/', '', substr($body, $i + 1, $end - $i - 1));
                $decoded = @hex2bin(strlen($hex) % 2 === 0 ? $hex : $hex . '0');
                $text .= $decoded === false ? '' : $decoded;
                $i = $end + 1;
                continue;
            }
        }

        // Tj / TJ / ' / " all show text, and T* moves to the next line.
        if ($ch === 'T') {
            if (($body[$i + 1] ?? '') === '*') {
                $text .= "\n";
                $i += 2;
                continue;
            }
            $i++;
            continue;
        }
        if ($ch === 'j' || $ch === 'J') {
            $text .= "\n";
            $i++;
            continue;
        }

        $i++;
    }

    return $text;
}

// Section headings recognised when grouping a resume's lines. Keyed by the
// normalised (lower-cased, punctuation-stripped) heading so that "PROFESSIONAL
// EXPERIENCE:", "2. Education" and "Work Experience" all match.
function resume_section_labels() {
    return [
        'summary' => 'Summary',
        'professional summary' => 'Summary',
        'career summary' => 'Summary',
        'executive summary' => 'Summary',
        'about me' => 'Summary',
        'about' => 'Summary',
        'profile' => 'Profile',
        'objective' => 'Objective',
        'career objective' => 'Objective',
        'professional objective' => 'Objective',
        'experience' => 'Experience',
        'work experience' => 'Experience',
        'professional experience' => 'Experience',
        'employment' => 'Experience',
        'employment history' => 'Experience',
        'work history' => 'Experience',
        'career history' => 'Experience',
        'education' => 'Education',
        'educational background' => 'Education',
        'academic background' => 'Education',
        'qualifications' => 'Education',
        'skills' => 'Skills',
        'technical skills' => 'Skills',
        'core skills' => 'Skills',
        'key skills' => 'Skills',
        'languages' => 'Languages',
        'certifications' => 'Certifications',
        'certificates' => 'Certifications',
        'licenses' => 'Certifications',
        'projects' => 'Projects',
        'selected projects' => 'Projects',
        'achievements' => 'Achievements',
        'awards' => 'Awards',
        'honors' => 'Achievements',
        'activities' => 'Activities',
        'volunteer experience' => 'Volunteering',
        'volunteering' => 'Volunteering',
        'publications' => 'Publications',
        'interests' => 'Interests',
        'hobbies' => 'Interests',
        'references' => 'References',
    ];
}

// Normalised heading for a line, or null when the line is not a heading.
// Returns ['key' => canonical key, 'label' => heading to display] so that a
// qualified heading such as "Additional Skills" still groups with Skills while
// keeping the wording the applicant chose.
function resume_heading_key($line) {
    $norm = strtolower(trim($line));
    $norm = preg_replace('/[^a-z &]+/', '', $norm);
    $norm = trim(preg_replace('/\s+/', ' ', $norm));
    $labels = resume_section_labels();

    if (isset($labels[$norm])) {
        $original = trim($line, " \t:.-");
        $all_caps = $original !== '' && $original === mb_strtoupper($original);
        return [
            'key'   => $norm,
            'label' => $all_caps ? $labels[$norm] : ucfirst(mb_strtolower($original)),
        ];
    }

    // "Additional Skills", "Technical Skills", "Core Competencies" - a known
    // heading with a qualifier in front of it.
    if ($norm !== '' && count(explode(' ', $norm)) <= 2) {
        foreach ($labels as $key => $label) {
            $prefix = trim(substr($norm, 0, -strlen($key)));
            if ($prefix !== '' && substr($norm, -strlen($key)) === $key) {
                $original = trim($line, " \t:.-");
                $all_caps = $original !== '' && $original === mb_strtoupper($original);
                return [
                    'key'   => $key,
                    'label' => $all_caps ? $label : ucfirst(mb_strtolower($original)),
                ];
            }
        }
    }

    return null;
}

// True for a line that carries a date or a date range on its own, which is what
// separates one resume entry from the next.
function resume_is_date_line($line) {
    $line = trim($line);
    if ($line === '' || mb_strlen($line) > 90) {
        return false;
    }
    if (preg_match('/\b(19|20)\d{2}\s*(?:-|\x{2013}|\x{2014}|to)\s*((19|20)\d{2}|present|current|now)\b/iu', $line)) {
        return true;
    }
    $has_month = preg_match('/\b(jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[a-z]*\b/i', $line);
    $has_year  = preg_match('/\b(19|20)\d{2}\b/', $line);
    if ($has_month && $has_year) {
        return true;
    }
    return $has_year && mb_strlen($line) <= 24 && !preg_match('/[.!?]/', $line);
}

// True for a short label such as "Senior Financial Advisor" - the kind of line
// that names a resume entry rather than describing it.
function resume_is_entry_title($line) {
    $line = trim($line);
    if ($line === '') {
        return false;
    }
    // Degree lines run far longer than a job title, so they are recognised on
    // the degree itself rather than on their length.
    if (preg_match('/\b(bachelor|master|doctor|ph\.?d|mba|associate|diploma|certificate|b\.s\.?|b\.a\.?|b\.com|m\.s\.?|m\.a\.?|m\.com)\b/i', $line)) {
        return true;
    }
    if (mb_strlen($line) > 60) {
        return false;
    }
    if (preg_match('/[.!?]$/', $line)) {
        return false;
    }
    if (substr_count($line, ' ') > 6) {
        return false;
    }
    // Bullet points often start with a lower-case letter.
    $first = mb_substr($line, 0, 1);
    return $first !== '' && $first === mb_strtoupper($first);
}

// True for the organisation / degree / school line under an entry title.
function resume_is_entry_subtitle($line) {
    $line = trim($line);
    if ($line === '' || mb_strlen($line) > 90 || preg_match('/[.!?]$/', $line)) {
        return false;
    }
    if (preg_match('/\b(university|college|institute|school|academy|hospital|inc|llc|ltd|plc|group|company|corp|corporation|technologies|technology|solutions|services|partners|bank|advisors|associates|labs|systems|management|holdings)\b/i', $line)) {
        return true;
    }
    // "Houston, TX" style location.
    if (preg_match('/\b[A-Z][a-z]+(?: [A-Z][a-z]+)*,\s*[A-Z]{2}\b/', $line)) {
        return true;
    }
    // An all-capitals company name.
    return $line === mb_strtoupper($line) && preg_match('/[A-Z]{2,}/', $line) === 1;
}

// Split a run of lines into the heading of a resume entry and the leftovers.
// Resumes are written either with the heading above the rest of the entry or
// with it sitting just above the date line, so both ends of the run are tried.
// Returns ['title','subtitle','rest'] with '' for a heading that could not be
// identified; 'rest' is the part that stays with the previous entry.
function resume_heading_block(array $lines) {
    $count = count($lines);
    $title = '';
    $subtitle = '';

    // Title first, then organisation or degree: the commonest layout.
    if ($count > 0 && resume_is_entry_title($lines[0])) {
        $title = array_shift($lines);
        if ($lines && resume_is_entry_subtitle($lines[0])) {
            $subtitle = array_shift($lines);
        }
        // A degree on one line with the honours and the school on the next two.
        elseif (count($lines) <= 2 && resume_is_entry_subtitle($lines[count($lines) - 1])) {
            $subtitle = array_pop($lines);
        }
    }

    // Organisation or degree first, then title, sitting at the end of the run
    // after the previous entry's bullets.
    if ($title === '' && $count >= 2
        && resume_is_entry_subtitle($lines[$count - 1])
        && resume_is_entry_title($lines[$count - 2])) {
        $subtitle = array_pop($lines);
        $title = array_pop($lines);
    }

    // A lone title at the end of the run.
    if ($title === '' && $count >= 1 && resume_is_entry_title($lines[$count - 1])) {
        $title = array_pop($lines);
        if ($lines && resume_is_entry_subtitle($lines[count($lines) - 1])) {
            $subtitle = array_pop($lines);
        }
    }

    return ['title' => $title, 'subtitle' => $subtitle, 'rest' => $lines];
}

// Group the lines of an experience / education style section into entries, one
// per date line, with everything between two dates kept as that entry's bullets.
function resume_build_entries(array $lines) {
    $lines = array_values(array_filter(array_map('trim', $lines), function ($line) {
        return $line !== '';
    }));

    $entries = [];
    $current = null;
    $group = [];
    $count = count($lines);

    for ($i = 0; $i < $count; $i++) {
        $line = $lines[$i];

        if (!resume_is_date_line($line)) {
            $group[] = $line;
            continue;
        }

        // The date line closes the previous entry; its heading is normally the
        // run of lines just above the date.
        $block = resume_heading_block($group);
        $group = [];
        $previous = $current;
        if ($previous !== null) {
            $previous['bullets'] = array_merge($previous['bullets'], $block['rest']);
            $entries[] = $previous;
        }

        $current = [
            'title'    => $block['title'],
            'subtitle' => $block['subtitle'],
            'meta'     => $line,
            // Lines above the date belong to this entry when it is the first one
            // in the section, because there is no earlier entry to hold them.
            'bullets'  => $previous === null ? $block['rest'] : [],
        ];

        if ($current['title'] === '') {
            // No heading above the date, so this entry's heading sits below it:
            // "July 2017 - 2020", "Financial Advisor", "Suntrust, New Orleans".
            if (isset($lines[$i + 1]) && resume_is_entry_title($lines[$i + 1])) {
                $current['title'] = $lines[$i + 1];
                $i++;
                if (isset($lines[$i + 1]) && resume_is_entry_subtitle($lines[$i + 1])) {
                    $current['subtitle'] = $lines[$i + 1];
                    $i++;
                }
            }
        }
    }

    if ($current !== null) {
        if ($group) {
            if ($current['title'] === '') {
                $block = resume_heading_block($group);
                $current['title'] = $block['title'];
                $current['subtitle'] = $block['subtitle'];
                $current['bullets'] = array_merge($current['bullets'], $block['rest']);
            } else {
                $current['bullets'] = array_merge($current['bullets'], $group);
            }
        }
        $entries[] = $current;
    }

    $kept = [];
    foreach ($entries as $entry) {
        if ($entry['title'] === '' && $entry['subtitle'] === '' && $entry['meta'] === '' && !$entry['bullets']) {
            continue;
        }
        $kept[] = $entry;
    }
    return $kept;
}

// Break a skills / languages style section into individual tags.
function resume_split_tags(array $lines) {
    $tags = [];
    foreach ($lines as $raw) {
        $line = trim($raw);
        if ($line === '') {
            continue;
        }
$parts = preg_split('/\s*[,;\|\x{2022}\x{00B7}\t]\s*/u', $line);
    // A sentence that happens to contain commas stays whole rather than
    // being shredded into meaningless tags.
    if (count($parts) > 14) {
        $parts = [$line];
    }
    foreach ($parts as $part) {
        $part = trim($part);
        $part = preg_replace('/^[\-\x{2013}\x{2014}\s]+|[\-\x{2013}\x{2014}\s]+$/u', '', $part);
            if ($part !== '') {
                $tags[] = $part;
            }
        }
    }
    return array_values(array_unique($tags));
}

// True for a short line that continues the paragraph above it.
function resume_is_continuation($line) {
    if (preg_match('/[.!?:]$/', $line)) {
        return false;
    }
    if (mb_strlen($line) > 60) {
        return false;
    }
    $first = mb_substr($line, 0, 1);
    return $first !== '' && $first === mb_strtoupper($first);
}

// Fold the lines of a prose section into paragraphs, re-attaching the short
// continuation lines (a wrapped sentence) to the line above them. A short first
// line is left alone: it is a headline, not a wrapped sentence.
function resume_paragraphs(array $lines) {
    $out = [];
    $buffer = '';
    foreach ($lines as $raw) {
        $line = trim($raw);
        if ($line === '') {
            if ($buffer !== '') {
                $out[] = $buffer;
                $buffer = '';
            }
            continue;
        }
        if ($buffer !== '' && substr_count($buffer, ' ') > 3 && resume_is_continuation($line)) {
            $buffer .= ' ' . $line;
            continue;
        }
        if ($buffer !== '') {
            $out[] = $buffer;
        }
        $buffer = $line;
    }
    if ($buffer !== '') {
        $out[] = $buffer;
    }
    return $out;
}

// Split a header line into the individual contact details it lists.
function resume_split_contacts($line) {
    $parts = preg_split('/\s*[|\x{2022}\x{00B7}\t;]\s*/u', $line);
    $out = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part !== '') {
            $out[] = $part;
        }
    }
    return $out;
}

// What kind of contact detail a value is, so it can be given the right icon.
// A phone number is only digits and separators - matching on "has three
// digits" would also catch a street address with a postcode in it.
function resume_contact_kind($value) {
    if (stripos($value, 'http') !== false || stripos($value, 'linkedin') !== false) {
        return 'link';
    }
    if (strpos($value, '@') !== false) {
        return 'email';
    }
    if (preg_match('/^[+]?[\d\s().\/-]{7,}$/', $value)) {
        return 'phone';
    }
    return 'place';
}

function resume_is_contact($line) {
    if (strpos($line, '@') !== false) {
        return true;
    }
    if (preg_match('/\D?\d[\d\s().-]{6,}\d/', $line)) {
        return true;
    }
    if (preg_match('/\b[A-Z][a-z]+(?: [A-Z][a-z]+)*,\s*[A-Z]{2}\b/', $line) === 1) {
        return true;
    }
    return preg_match('/(https?:\/\/|www\.|linkedin|github|behance|dribbble|portfolio)/i', $line) === 1;
}

// Turn a resume's plain text into a header plus an ordered list of sections, so
// it can be rendered as a document rather than as a file dump. Purely heuristic:
// it keys off the section headings and date lines resumes use, and anything it
// cannot classify is kept as text so nothing is ever dropped.
function resume_parse($text) {
    $header_lines = [];
    $sections = [];
    $current = null;

    foreach (preg_split('/\r\n|\r|\n/', (string)$text) as $line) {
        $line = trim(str_replace("\xC2\xA0", ' ', $line));
        $heading = resume_heading_key($line);
        if ($heading !== null) {
            $current = $heading['key'];
            if (!isset($sections[$current])) {
                $sections[$current] = ['label' => $heading['label'], 'lines' => []];
            }
            continue;
        }
        if ($current === null) {
            $header_lines[] = $line;
        } else {
            $sections[$current]['lines'][] = $line;
        }
    }

    // Sections that are laid out as dated entries, as tags, and as prose.
    $entry_keys = ['experience', 'work experience', 'professional experience', 'employment',
        'employment history', 'work history', 'career history', 'education', 'educational background',
        'academic background', 'projects', 'selected projects', 'volunteering', 'volunteer experience',
        'achievements', 'awards', 'activities'];
    $tag_keys = ['skills', 'technical skills', 'core skills', 'key skills', 'languages',
        'certifications', 'certificates', 'licenses', 'interests', 'hobbies', 'honors'];
    $para_keys = ['summary', 'professional summary', 'career summary', 'executive summary', 'about me',
        'about', 'profile', 'objective', 'career objective', 'professional objective',
        'publications', 'references'];

    $out = [];
    foreach ($sections as $key => $section) {
        $lines = $section['lines'];
        if (in_array($key, $entry_keys, true)) {
            $type = 'entries';
            $items = resume_build_entries($lines);
        } elseif (in_array($key, $tag_keys, true)) {
            $type = 'tags';
            $items = resume_split_tags($lines);
        } elseif (in_array($key, $para_keys, true)) {
            $type = 'paragraphs';
            $items = resume_paragraphs($lines);
        } else {
            // Unknown heading: dated lines mean entries, otherwise prose.
            $items = resume_build_entries($lines);
            $dated = false;
            foreach ($items as $item) {
                if ($item['meta'] !== '') {
                    $dated = true;
                    break;
                }
            }
            if ($dated) {
                $type = 'entries';
            } else {
                $type = 'paragraphs';
                $items = resume_paragraphs($lines);
            }
        }
        if (!$items) {
            continue;
        }
        $out[] = ['label' => $section['label'], 'type' => $type, 'items' => $items];
    }

    // Header: name, then an optional headline, then the contact details.
    $name = '';
    $title = '';
    $contacts = [];
    foreach ($header_lines as $line) {
        if ($line === '') {
            continue;
        }
        if ($name === '' && !resume_is_contact($line) && !resume_is_date_line($line)) {
            $name = $line;
            continue;
        }
        if ($title === '' && !resume_is_contact($line) && resume_is_entry_title($line)) {
            $title = $line;
            continue;
        }
        foreach (resume_split_contacts($line) as $part) {
            // Prose that happens to sit above the first heading is body text, not
            // a contact detail, so it is left for the sections below.
            if (resume_is_contact($part)) {
                $contacts[] = $part;
            }
        }
    }

    return [
        'name'     => $name,
        'title'    => $title,
        'contacts' => array_values(array_unique($contacts)),
        'sections' => $out,
    ];
}

// Load one application with the applicant behind it, restricted to the employer
// that posted the job. Returns null when the application belongs to somebody
// else, so no page can read or act on another company's applicants.
function employer_get_application($app_id, $employer_id) {
    $app = getDB()->prepare("
        SELECT a.id, a.status, a.created_at, a.cover_letter, a.resume_path, a.applicant_id, a.job_id,
               j.title as job_title, j.location as job_location,
               u.name as applicant_name, u.email as applicant_email, u.phone as applicant_phone,
               ap.professional_title, ap.resume as profile_resume, ap.address,
               ap.skills, ap.experience, ap.education, ap.career_objective
        FROM applications a
        JOIN jobs j ON a.job_id = j.id
        JOIN users u ON a.applicant_id = u.id
        LEFT JOIN applicant_profiles ap ON ap.user_id = u.id
        WHERE a.id = ? AND j.employer_id = ?
    ")->execute([$app_id, $employer_id])->fetch();

    return $app ?: null;
}

/**
 * Read one application without the employer ownership check.
 *
 * employer_get_application() scopes to a single company, which is what an
 * employer needs and exactly what an admin must not be limited to. Same
 * columns, no ownership filter.
 */
function admin_get_application($app_id) {
    $app = getDB()->prepare("
        SELECT a.id, a.status, a.created_at, a.cover_letter, a.resume_path, a.applicant_id, a.job_id,
               j.title as job_title, j.location as job_location,
               u.name as applicant_name, u.email as applicant_email, u.phone as applicant_phone,
               ap.professional_title, ap.resume as profile_resume, ap.address,
               ap.skills, ap.experience, ap.education, ap.career_objective
        FROM applications a
        JOIN jobs j ON a.job_id = j.id
        JOIN users u ON a.applicant_id = u.id
        LEFT JOIN applicant_profiles ap ON ap.user_id = u.id
        WHERE a.id = ?
    ")->execute([(int)$app_id])->fetch();

    return $app ?: null;
}

// Move one of the employer's applications to a new status. The job the
// application belongs to is checked first, so a crafted id cannot reach another
// company's applicants. Returns the status that was set, or null when the action
// is unknown or the application is not this employer's.
function employer_set_application_status($app_id, $employer_id, $action) {
    $allowed = ['pending', 'under review', 'shortlisted', 'interview', 'accepted', 'hired', 'rejected'];
    if (!in_array($action, $allowed, true)) {
        return null;
    }

    $db = getDB();
    $owns = $db->prepare("
        SELECT a.id
        FROM applications a
        JOIN jobs j ON a.job_id = j.id
        WHERE a.id = ? AND j.employer_id = ?
    ")->execute([$app_id, $employer_id])->fetchColumn();

    if (!$owns) {
        return null;
    }

    $db->prepare("UPDATE applications SET status = ? WHERE id = ?")->execute([$action, $app_id]);
    return $action;
}

// The labels used for the application status, mapped to the badge each one wears
// and the sentence shown after the employer changes it.
function application_status_meta() {
    return [
        'pending'     => ['badge' => 'badge-pending', 'label' => 'Pending', 'message' => 'is back in the new queue'],
        'under review' => ['badge' => 'badge-under-review', 'label' => 'Under Review', 'message' => 'is now under review'],
        'shortlisted' => ['badge' => 'badge-shortlisted', 'label' => 'Shortlisted', 'message' => 'has been shortlisted'],
        'interview'   => ['badge' => 'badge-interview', 'label' => 'Interview', 'message' => 'has moved to interview'],
        'accepted'    => ['badge' => 'badge-accepted', 'label' => 'Accepted', 'message' => 'has been accepted'],
        'hired'       => ['badge' => 'badge-hired', 'label' => 'Hired', 'message' => 'has been hired'],
        'rejected'    => ['badge' => 'badge-rejected', 'label' => 'Rejected', 'message' => 'has been rejected'],
    ];
}

// Resolve a stored resume reference to a real file on disk. Applications keep
// either a bare file name or an "uploads/resumes/..." path, and both values come
// from user input, so only the file name is honoured.
function resume_resolve_path($reference) {
    $reference = trim((string)$reference);
    if ($reference === '') {
        return '';
    }
    $path = __DIR__ . '/uploads/resumes/' . basename(str_replace('\\', '/', $reference));
    return is_file($path) ? $path : '';
}

/**
 * Build the parts of the resume preview: the document parsed out of the file, the
 * embedded fallback for formats only the browser can render, and the note saying
 * the layout was rebuilt from the upload. Shared by the applicant preview and the
 * employer's view of an applicant's resume so both print the same document.
 *
 * $name, $headline and $contacts are fallbacks for whatever the file does not
 * carry, so the header never ends up blank or printed twice.
 */
function resume_preview_parts($path, $name = '', $headline = '', array $contacts = [], $stream_url = '', $download_url = '') {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $bytes = (int)filesize($path);
    $size = $bytes >= 1048576
        ? number_format($bytes / 1048576, 1) . ' MB'
        : max(1, (int)round($bytes / 1024)) . ' KB';

    $text = resume_extract_text($path);
    $has_doc = resume_text_is_readable($text);
    $parsed = resume_parse($text);

    // No heading the parser recognises: show the text itself as the body and let
    // the header fall back to the profile, so nothing is printed twice.
    if (!$parsed['sections']) {
        $parsed['name'] = '';
        $parsed['title'] = '';
        $parsed['contacts'] = [];
        if ($has_doc) {
            $parsed['sections'][] = [
                'label' => 'Resume',
                'type'  => 'paragraphs',
                'items' => resume_paragraphs(preg_split('/\r\n|\r|\n/', $text)),
            ];
        }
    }

    $name = $parsed['name'] !== '' ? $parsed['name'] : trim((string)$name);
    $headline = $parsed['title'] !== '' ? $parsed['title'] : trim((string)$headline);
    if (!$parsed['contacts']) {
        $contacts = array_values(array_filter(array_map('trim', $contacts)));
    } else {
        $contacts = $parsed['contacts'];
    }

    $contact_icons = ['email' => '&#9993;', 'phone' => '&#9742;', 'link' => '&#128279;', 'place' => '&#128205;'];
    $contacts_html = '';
    foreach (array_slice($contacts, 0, 6) as $contact) {
        $kind = resume_contact_kind($contact);
        $contacts_html .= '<li><span class="rv-contact-icon">'
            . $contact_icons[$kind] . '</span>' . e($contact) . '</li>';
    }

    $head_html = '';
    if ($name !== '') {
        $head_html .= '<h1 class="rv-name">' . e($name) . '</h1>';
    }
    if ($headline !== '') {
        $head_html .= '<p class="rv-headline">' . e($headline) . '</p>';
    }
    if ($contacts_html !== '') {
        $head_html .= '<ul class="rv-contacts">' . $contacts_html . '</ul>';
    }
    // Wrapped in the printed-page header block; skipped entirely when the file and
    // the profile offer nothing, so no rule line is drawn above an empty sheet.
    if ($head_html !== '') {
        $head_html = '<header class="rv-head">' . $head_html . '</header>';
    }

    $sections_html = '';
    foreach ($parsed['sections'] as $section) {
        $sections_html .= '<section class="rv-sec">'
            . '<h2 class="rv-sec-title">' . e($section['label']) . '</h2>';

        if ($section['type'] === 'tags') {
            $tags = '';
            foreach ($section['items'] as $tag) {
                $tags .= '<li>' . e($tag) . '</li>';
            }
            $sections_html .= '<ul class="rv-tags">' . $tags . '</ul>';
        } elseif ($section['type'] === 'paragraphs') {
            foreach ($section['items'] as $paragraph) {
                $sections_html .= '<p class="rv-para">' . e($paragraph) . '</p>';
            }
        } else {
            foreach ($section['items'] as $entry) {
                $entry_html = '';
                if ($entry['title'] !== '') {
                    $entry_html .= '<h3 class="rv-entry-title">' . e($entry['title']) . '</h3>';
                }
                if ($entry['subtitle'] !== '') {
                    $entry_html .= '<p class="rv-entry-sub">' . e($entry['subtitle']) . '</p>';
                }
                if ($entry['meta'] !== '') {
                    $entry_html .= '<p class="rv-entry-meta">' . e($entry['meta']) . '</p>';
                }
                if ($entry['bullets']) {
                    $entry_html .= '<ul class="rv-bullets">';
                    foreach ($entry['bullets'] as $bullet) {
                        $entry_html .= '<li>' . e($bullet) . '</li>';
                    }
                    $entry_html .= '</ul>';
                }
                $sections_html .= '<article class="rv-entry">' . $entry_html . '</article>';
            }
        }

        $sections_html .= '</section>';
    }

    // When nothing could be parsed the file itself is embedded instead, which only
    // the browser can render for the formats it knows.
    $can_embed = in_array($ext, ['pdf', 'txt'], true);

    // Note explaining that the preview was rebuilt from the file, rather than shown
    // as the file. Emitted only when it applies, so there is no empty blue bar.
    $note_html = '';
    if ($has_doc) {
        $note_html = '<div class="rv-note"><span class="rv-note-icon">&#9432;</span><span>'
            . e('These sections were read out of the uploaded ' . strtoupper($ext)
                . ' file, so the layout here may not match the original document.')
            . '</span></div>';
    }

    // The embedded viewer is emitted only when it is actually shown: a display:none
    // iframe still downloads the file, which wastes the viewer's bandwidth.
    $viewer_html = '';
    if (!$has_doc && $can_embed) {
        $viewer_html = '<div class="rv-viewer"><iframe src="' . e($stream_url)
            . '" title="Resume preview" loading="lazy"></iframe></div>';
    }

    $empty_html = '';
    if (!$has_doc && !$can_embed) {
        $empty_html = '<div class="rv-empty">'
            . '<div class="rv-empty-icon">&#128196;</div>'
            . '<h3>Preview unavailable</h3>'
            . '<p class="text-muted">This file format cannot be laid out here. Open or download it to read the contents.</p>'
            . '<a class="btn btn-primary" href="' . e($download_url) . '">Download Resume</a>'
            . '</div>';
    }

    return [
        'meta' => new RawHtml(
            strtoupper($ext) . ' &middot; ' . e($size) . ' &middot; Updated ' . e(date('M j, Y', filemtime($path)))
        ),
        'doc' => new RawHtml($has_doc
            ? '<article class="rv-doc">' . $head_html . $sections_html . '</article>'
            : ''),
        'note' => new RawHtml($note_html),
        'viewer' => new RawHtml($viewer_html),
        'empty' => new RawHtml($empty_html),
    ];
}

// MIME type for streaming a resume file back to the browser.
function resume_mime_type($path) {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $map = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'txt' => 'text/plain; charset=UTF-8',
    ];
    return $map[$ext] ?? 'application/octet-stream';
}

// Name to offer the browser for a streamed resume. The stored name is generated
// by the upload handler, but it is still attacker-influenced data, so quotes,
// separators and control characters are dropped before it reaches a header.
function resume_stream_filename($path) {
    $name = preg_replace('/[\x00-\x1F\x7F"\\\\\/]+/', '', basename((string)$path));
    $name = trim($name);
    return $name !== '' ? $name : 'resume';
}

// Build a styled flash message from $_SESSION['error'] / $_SESSION['success']
function flash_message() {
    if (isset($_SESSION['error']) && $_SESSION['error'] !== '') {
        $msg = $_SESSION['error'];
        unset($_SESSION['error']);
        return '<div class="form-error">' . htmlspecialchars($msg) . '</div>';
    }
    if (isset($_SESSION['success']) && $_SESSION['success'] !== '') {
        $msg = $_SESSION['success'];
        unset($_SESSION['success']);
        return '<div class="form-success">' . htmlspecialchars($msg) . '</div>';
    }
    return '';
}

/**
 * Redirect after a state change, carrying a one-shot flash message.
 * Query-string notices (?success=...) were never rendered by the pages that
 * produced them, so every action looked like a silent reload.
 *
 * @param string $url     destination
 * @param string $message notice to show on the destination page
 * @param string $level   'success' or 'error'
 */
/**
 * Job types the schema accepts. jobs.type is an enum, so a value outside this
 * list is truncated to an empty string on write and the posting disappears from
 * every type filter. Forms build their options from here and controllers normalise
 * the submitted value against it.
 */
function job_types() {
    return ['Full-time', 'Part-time', 'Internship', 'Remote'];
}

function job_type_options_html($current = '') {
    $out = '';
    foreach (job_types() as $type) {
        $selected = (strcasecmp($type, (string)$current) === 0) ? ' selected' : '';
        $out .= '<option value="' . e($type) . '"' . $selected . '>' . e($type) . '</option>';
    }
    return $out;
}

function normalise_job_type($value) {
    foreach (job_types() as $type) {
        if (strcasecmp($type, trim((string)$value)) === 0) {
            return $type;
        }
    }
    return 'Full-time';
}

/**
 * Minimum-salary bands offered by the job search filters, lowest first. The
 * labels carry no currency symbol on purpose: job cards render salaries as bare
 * formatted numbers, so prefixing "$" here would invent a currency the rest of
 * the site never claims.
 */
function salary_bands() {
    return [
        40000  => '40,000+',
        60000  => '60,000+',
        80000  => '80,000+',
        100000 => '100,000+',
    ];
}

/**
 * Experience is free text in the jobs table, so each level maps to the terms an
 * employer might plausibly have typed. Matching is a case-insensitive substring
 * test so free-form entries such as "3-5 years (Mid-level)" still match.
 */
function experience_levels() {
    return [
        'Entry-level' => ['entry', 'fresher', 'fresh graduate', 'no experience', '0 year', 'junior'],
        'Mid-level'   => ['mid', 'intermediate', '1 year', '2 year', '3 year'],
        'Senior'      => ['senior', 'lead', 'principal', '5 year', '7 year'],
        'Executive'   => ['executive', 'director', 'head of', 'chief'],
    ];
}

function salary_band_options_html($current = '') {
    $out = '<option value="">Any salary</option>';
    foreach (salary_bands() as $value => $label) {
        $selected = ((string)$current === (string)$value) ? ' selected' : '';
        $out .= '<option value="' . $value . '"' . $selected . '>' . e($label) . '</option>';
    }
    return $out;
}

function experience_level_options_html($current = '') {
    $out = '<option value="">Any experience</option>';
    foreach (experience_levels() as $label => $terms) {
        $selected = (strcasecmp($label, (string)$current) === 0) ? ' selected' : '';
        $out .= '<option value="' . e($label) . '"' . $selected . '>' . e($label) . '</option>';
    }
    return $out;
}

/**
 * Job Type filter options, with an "All Types" reset. Kept separate from
 * job_type_options_html() because that one is for required dropdowns (posting a
 * job), where an empty option would be invalid. Both search pages build this
 * filter from here so their panels stay identical.
 */
function job_type_filter_options_html($current = '') {
    $out = '<option value="">All Types</option>';
    foreach (job_types() as $type) {
        $selected = (strcasecmp($type, (string)$current) === 0) ? ' selected' : '';
        $out .= '<option value="' . e($type) . '"' . $selected . '>' . e($type) . '</option>';
    }
    return $out;
}

/**
 * Turns the raw job search query string into a validated filter set, dropping
 * anything not in the allowed list so a crafted URL cannot push odd values into
 * the SQL below.
 */
function normalise_job_filters(array $get) {
    $search   = trim((string)($get['search'] ?? ''));
    $location = trim((string)($get['location'] ?? ''));

    $category = trim((string)($get['category'] ?? ''));
    $category = ctype_digit($category) ? (int)$category : 0;

    $type = trim((string)($get['type'] ?? ''));
    if (strcasecmp($type, 'Full-time') !== 0 && strcasecmp($type, 'Part-time') !== 0
        && strcasecmp($type, 'Internship') !== 0 && strcasecmp($type, 'Remote') !== 0) {
        $type = '';
    }

    $salary = trim((string)($get['min_salary'] ?? ''));
    if ($salary === '' || !array_key_exists((int)$salary, salary_bands()) || (string)(int)$salary !== $salary) {
        $salary = '';
    }

    $experience = trim((string)($get['experience'] ?? ''));
    if (!array_key_exists($experience, experience_levels())) {
        $experience = '';
    }

    return [
        'search'     => $search,
        'location'   => $location,
        'category'   => $category,
        'type'       => $type,
        'min_salary' => $salary,
        'experience' => $experience,
    ];
}

/**
 * Builds the shared WHERE fragments for the job search so the result list and
 * the "showing X of Y" total can never drift apart. Returns [sql, params].
 *
 * The search term spans the employer and category names as well as the job
 * columns, so callers must include the users/categories joins in their FROM
 * clause. $extra_columns lets a caller widen that set without editing here.
 */
function job_search_where(array $filters, $extra_columns = []) {
    $j = 'j';
    $where = [];
    $params = [];

    if ($filters['search'] !== '') {
        $like = '%' . $filters['search'] . '%';
        $columns = array_merge([
            "$j.title", "$j.skills", "$j.description", "$j.location",
            "$j.type", 'u.name', 'c.name',
        ], $extra_columns);
        $where[] = '(' . implode(' OR ', array_map(fn($c) => "$c LIKE ?", $columns)) . ')';
        foreach ($columns as $ignored) {
            $params[] = $like;
        }
    }
    if ($filters['location'] !== '') {
        $where[] = "$j.location LIKE ?";
        $params[] = '%' . $filters['location'] . '%';
    }
    if ($filters['category'] > 0) {
        $where[] = "$j.category_id = ?";
        $params[] = $filters['category'];
    }
    if ($filters['type'] !== '') {
        $where[] = "$j.type = ?";
        $params[] = $filters['type'];
    }
    if ($filters['min_salary'] !== '') {
        // A band filters on the top of the range, so "60,000+" keeps jobs whose
        // ceiling reaches 60k. Jobs with an undisclosed salary drop out.
        $where[] = "$j.salary_max >= ?";
        $params[] = (int)$filters['min_salary'];
    }
    if ($filters['experience'] !== '') {
        $clause = [];
        foreach (experience_levels()[$filters['experience']] as $term) {
            $clause[] = "LOWER($j.experience) LIKE ?";
            $params[] = '%' . strtolower($term) . '%';
        }
        $where[] = '(' . implode(' OR ', $clause) . ')';
    }

    return [$where ? ' AND ' . implode(' AND ', $where) : '', $params];
}

function redirect_with_flash($url, $message = '', $level = 'success') {
    if ($message !== '') {
        $_SESSION[$level === 'error' ? 'error' : 'success'] = $message;
    }
    header('Location: ' . $url);
    exit;
}

/**
 * Draw a grouped bar chart as inline SVG. Rendering server-side keeps the reports
 * page free of any charting library and of JavaScript.
 *
 * $labels  x axis captions, one per group
 * $series  [ ['name' => 'Applicants', 'color' => '#2563eb', 'values' => [1, 2, ...]], ... ]
 */
function report_bar_chart(array $labels, array $series, $empty_text) {
    static $chart_no = 0;
    $chart_no++;

    $width = 640;
    $height = 280;
    $pad_left = 42;
    $pad_right = 14;
    $pad_top = 26;
    $pad_bottom = 38;
    $plot_w = $width - $pad_left - $pad_right;
    $plot_h = $height - $pad_top - $pad_bottom;

    $max = 0;
    $total = 0;
    foreach ($series as $s) {
        foreach ($s['values'] as $v) {
            $max = max($max, (int)$v);
            $total += (int)$v;
        }
    }
    if ($total === 0) {
        return '<div class="rp-empty">' . e($empty_text) . '</div>';
    }

    // Grow the axis until four gridlines cover the data, so labels stay whole.
    $step = 1;
    while ($max > $step * 4) {
        $step *= 2;
    }
    $axis_max = max($step * 4, (int)ceil($max / $step) * $step);

    $uid = 'rpg' . $chart_no;

    // One gradient per series, so bars read as solid at the base and lighter at
    // the top. Ids are unique per chart because several charts share a page.
    $defs = '<defs>';
    foreach ($series as $si => $s) {
        $id = $uid . 'g' . $si;
        $defs .= '<linearGradient id="' . $id . '" x1="0" y1="0" x2="0" y2="1">'
            . '<stop offset="0%" stop-color="' . e($s['color']) . '" stop-opacity="1" />'
            . '<stop offset="100%" stop-color="' . e($s['color']) . '" stop-opacity="0.55" />'
            . '</linearGradient>';
    }
    $defs .= '</defs>';

    $svg = '<svg class="rp-chart" viewBox="0 0 ' . $width . ' ' . $height . '" role="img" preserveAspectRatio="xMidYMid meet">'
        . $defs;

    for ($i = 0; $i <= 4; $i++) {
        $value = (int)round($axis_max * $i / 4);
        $y = $pad_top + $plot_h - ($plot_h * $i / 4);
        $svg .= '<line x1="' . $pad_left . '" y1="' . round($y, 2) . '" x2="' . ($width - $pad_right) . '" y2="' . round($y, 2) . '" class="rp-grid" />';
        $svg .= '<text x="' . ($pad_left - 10) . '" y="' . round($y + 4, 2) . '" class="rp-axis-label" text-anchor="end">' . $value . '</text>';
    }

    // Baseline sits above the gridlines so it reads as the x axis.
    $svg .= '<line x1="' . $pad_left . '" y1="' . ($pad_top + $plot_h) . '" x2="' . ($width - $pad_right) . '" y2="' . ($pad_top + $plot_h) . '" class="rp-axis" />';

    $groups = count($labels);
    $group_w = $groups > 0 ? $plot_w / $groups : 0;
    $bars_per_group = max(1, count($series));
    $bar_w = max(5, ($group_w * 0.6) / $bars_per_group);

    foreach ($labels as $gi => $label) {
        $group_x = $pad_left + $group_w * $gi;
        $bars_w = $bar_w * $bars_per_group;
        $start_x = $group_x + ($group_w - $bars_w) / 2;

        foreach ($series as $si => $s) {
            $value = (int)($s['values'][$gi] ?? 0);
            $x = $start_x + $bar_w * $si + 1.5;
            $bar_w_used = $bar_w - 3;
            $bar_h = $plot_h * $value / $axis_max;
            $y = $pad_top + $plot_h - $bar_h;

            if ($value > 0) {
                $svg .= '<rect class="rp-bar" x="' . round($x, 2) . '" y="' . round($y, 2) . '" width="' . round($bar_w_used, 2) . '" height="' . round($bar_h, 2) . '" rx="4" ry="4" fill="url(#' . $uid . 'g' . $si . ')">'
                    . '<title>' . e($label . ' · ' . $s['name'] . ': ' . number_format($value)) . '</title>'
                    . '</rect>';
                $svg .= '<text x="' . round($x + $bar_w_used / 2, 2) . '" y="' . round($y - 7, 2) . '" class="rp-bar-label" text-anchor="middle">' . number_format($value) . '</text>';
            } else {
                // Keep a hairline for zero so an empty month still reads as a column.
                $svg .= '<rect class="rp-bar-zero" x="' . round($x, 2) . '" y="' . ($pad_top + $plot_h - 2) . '" width="' . round($bar_w_used, 2) . '" height="2" rx="1" fill="' . e($s['color']) . '">'
                    . '<title>' . e($label . ' · ' . $s['name'] . ': 0') . '</title>'
                    . '</rect>';
            }
        }

        $svg .= '<text x="' . round($group_x + $group_w / 2, 2) . '" y="' . ($pad_top + $plot_h + 22) . '" class="rp-axis-label rp-axis-month" text-anchor="middle">' . e($label) . '</text>';
    }

    $svg .= '</svg>';

    // Legend spells out what each colour stands for, plus its six-month total.
    // Each series may carry a 'desc' explaining the colour in plain words.
    $legend = '';
    foreach ($series as $si => $s) {
        $sum = 0;
        foreach ($s['values'] as $v) {
            $sum += (int)$v;
        }
        $peak = 0;
        foreach ($s['values'] as $v) {
            $peak = max($peak, (int)$v);
        }
        $legend .= '<div class="rp-key">'
            . '<span class="rp-swatch" style="background:' . e($s['color']) . '"></span>'
            . '<span class="rp-key-text">'
            . '<span class="rp-key-name">' . e($s['name']) . '</span>'
            . (isset($s['desc']) && $s['desc'] !== '' ? '<span class="rp-key-desc">' . e($s['desc']) . '</span>' : '')
            . '</span>'
            . '<span class="rp-key-stats"><span class="rp-key-total">' . number_format($sum) . '</span>'
            . '<span class="rp-key-peak">peak ' . number_format($peak) . '</span></span>'
            . '</div>';
    }

    return '<div class="rp-chart-wrap">'
        . $svg
        . '<div class="rp-legend"><span class="rp-legend-title">Legend</span>' . $legend . '</div>'
        . '</div>';
}

/**
 * Validate and persist an admin's own profile. Shared by pages/admin/profile.php
 * and pages/admin/edit-profile.php. Returns an error string, or null on success.
 */
function admin_profile_save($db, $user_id, array $post, array $file, $current_photo) {
    $name    = trim($post['name'] ?? '');
    $email   = trim($post['email'] ?? '');
    $phone   = trim($post['phone'] ?? '');
    $title   = trim($post['title'] ?? '');
    $dept    = trim($post['department'] ?? '');
    $address = trim($post['address'] ?? '');

    if ($name === '' || $email === '') {
        return 'Name and email are required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Enter a valid email address.';
    }
    if (mb_strlen($name) > 120) {
        return 'Name must be 120 characters or fewer.';
    }

    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $user_id]);
    if ($stmt->fetch()) {
        return 'Email already in use by another account.';
    }

    // Photo upload. The extension is allow-listed, the real MIME type is
    // verified, and the stored name is generated by us, so a crafted filename
    // cannot escape uploads/photos or smuggle in a non-image.
    $photo_name = $current_photo !== '' ? $current_photo : null;
    if (!empty($file['name'])) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if ($file['error'] !== UPLOAD_ERR_OK || !in_array($ext, $allowed, true)) {
            return 'Photo must be a JPG, PNG, GIF or WEBP image.';
        }
        if ($file['size'] > 2 * 1024 * 1024) {
            return 'Photo must be smaller than 2MB.';
        }
        $expected = [
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png'  => ['image/png'],
            'gif'  => ['image/gif'],
            'webp' => ['image/webp'],
        ];
        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = (string)finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
            }
        }
        if (!in_array($mime, $expected[$ext], true)) {
            return 'That file is not a valid image.';
        }

        $dir = __DIR__ . '/uploads/photos/';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $generated = 'admin_' . $user_id . '_' . time() . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . $generated)) {
            return 'Failed to save the photo.';
        }
        if ($current_photo !== '' && is_file($dir . $current_photo)) {
            @unlink($dir . $current_photo);
        }
        $photo_name = $generated;
    }

    $db->beginTransaction();
    try {
        $db->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?")
            ->execute([$name, $email, $phone, $user_id]);

        $stmt = $db->prepare("UPDATE admin_profiles SET
            professional_title = ?, department = ?, address = ?, photo = ?
            WHERE user_id = ?");
        $stmt->execute([$title, $dept, $address, $photo_name, $user_id]);

        if ($stmt->rowCount() === 0) {
            $db->prepare("INSERT INTO admin_profiles
                (user_id, professional_title, department, address, photo)
                VALUES (?,?,?,?,?)")
                ->execute([$user_id, $title, $dept, $address, $photo_name]);
        }

        $db->commit();
    } catch (PDOException $e) {
        $db->rollBack();
        return 'Update failed. Please try again.';
    }

    $_SESSION['name'] = $name;
    $_SESSION['success'] = 'Profile updated successfully.';
    return null;
}

// Permanently delete a user account together with every record and file
// that belongs to it (profiles, settings, jobs, applications, saved jobs,
// uploads). Returns true on success, false if the account does not exist.
function delete_user_account($user_id) {
    $user_id = (int)$user_id;
    if ($user_id <= 0) {
        return false;
    }
    $db = getDB();

    $user = $db->prepare("SELECT * FROM users WHERE id = ?")->execute([$user_id])->fetch();
    if (!$user) {
        return false;
    }

    // Admin accounts are protected: they can only be removed directly
    // at the database level (see BEFORE DELETE trigger on `users`).
    if ($user['role'] === 'admin') {
        return false;
    }

    $uploads_dir = __DIR__ . '/uploads';

    // Collect files owned by this user so they are removed too.
    $files = [];
    if ($user['role'] === 'applicant') {
        $profile = $db->prepare("SELECT resume, photo FROM applicant_profiles WHERE user_id = ?")->execute([$user_id])->fetch();
        if ($profile) {
            if (!empty($profile['resume']))  $files[] = $uploads_dir . '/resumes/' . $profile['resume'];
            if (!empty($profile['photo']))   $files[] = $uploads_dir . '/photos/' . $profile['photo'];
        }
    } elseif ($user['role'] === 'employer') {
        $profile = $db->prepare("SELECT logo, cover FROM employer_profiles WHERE user_id = ?")->execute([$user_id])->fetch();
        if ($profile) {
            if (!empty($profile['logo']))  $files[] = $uploads_dir . '/logos/' . $profile['logo'];
            if (!empty($profile['cover'])) $files[] = $uploads_dir . '/covers/' . $profile['cover'];
        }
    }

    // Delete the user row. Foreign keys (ON DELETE CASCADE) remove
    // applicant/employer profiles, user_settings, saved_jobs,
    // applications, and the employer's jobs + their received applications.
    $db->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);

    // Remove uploaded files from disk (best-effort).
    foreach ($files as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }

    return true;
}

// Destroy the current session (used after account deletion / logout).
function destroy_session() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

// Dummy data for demo (replace with DB queries)
function get_jobs() {
    return [
        ['id'=>1, 'title'=>'Senior Frontend Developer', 'company'=>'TechNova Inc.', 'location'=>'San Francisco, CA', 'salary'=>'$95k – $130k', 'type'=>'Full-time', 'tags'=>['React','TypeScript','GraphQL']],
        ['id'=>2, 'title'=>'Marketing Manager', 'company'=>'GrowthBridge', 'location'=>'Austin, TX', 'salary'=>'$70k – $90k', 'type'=>'Full-time', 'tags'=>['SEO','Content','Analytics']],
        ['id'=>3, 'title'=>'Registered Nurse - ICU', 'company'=>'Valley Medical Center', 'location'=>'Phoenix, AZ', 'salary'=>'$75k – $95k', 'type'=>'Full-time', 'tags'=>['ICU','BLS','ACLS']],
        ['id'=>4, 'title'=>'Data Analyst', 'company'=>'Datasphere Co.', 'location'=>'Chicago, IL', 'salary'=>'$65k – $85k', 'type'=>'Full-time', 'tags'=>['SQL','Python','Tableau']],
        ['id'=>5, 'title'=>'UX/UI Designer', 'company'=>'PixelCraft Studio', 'location'=>'New York, NY', 'salary'=>'$80k – $110k', 'type'=>'Full-time', 'tags'=>['Figma','User Research','Prototyping']],
    ];
}
function get_applications() {
    return [
        ['job'=>'Senior Frontend Developer', 'company'=>'TechNova Inc.', 'date'=>'Jul 14, 2025', 'status'=>'Shortlisted'],
        ['job'=>'Marketing Manager', 'company'=>'GrowthBridge', 'date'=>'Jul 12, 2025', 'status'=>'Under Review'],
        ['job'=>'Data Analyst', 'company'=>'Datasphere Co.', 'date'=>'Jul 7, 2025', 'status'=>'Rejected'],
    ];
}
function get_companies() {
    return [
        ['name'=>'TechNova Inc.', 'industry'=>'Technology · San Francisco, CA', 'desc'=>'Next‑gen developer tools used by 40,000 engineers.', 'jobs'=>14],
        ['name'=>'GrowthBridge', 'industry'=>'Marketing · Austin, TX', 'desc'=>'Data‑driven B2B SaaS marketing strategies.', 'jobs'=>6],
        ['name'=>'Valley Medical Center', 'industry'=>'Healthcare · Phoenix, AZ', 'desc'=>'Leading regional hospital network.', 'jobs'=>31],
    ];
}
function get_active_jobs_employer() {
    return [
        ['title'=>'Senior Frontend Developer', 'apps'=>45, 'deadline'=>'Dec 31, 2025', 'status'=>'Active'],
        ['title'=>'Backend Engineer', 'apps'=>38, 'deadline'=>'Jan 15, 2026', 'status'=>'Active'],
    ];
}