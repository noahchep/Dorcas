<?php
session_start();
require_once dirname(__DIR__) . '/vendor/autoload.php';

use Phpml\Classification\NaiveBayes;
use Phpml\Tokenization\WhitespaceTokenizer;
use Phpml\FeatureExtraction\TokenCountVectorizer;
use Phpml\FeatureExtraction\TfIdfTransformer;

/* ===============================
    1. DATABASE & SESSION SETUP
================================ */
$conn = mysqli_connect("localhost", "root", "", "portal-asisstant-ai");
if (!$conn) { die("Connection failed: " . mysqli_connect_error()); }

$sess_id = session_id(); 
$user_input = strtolower(trim($_POST['message'] ?? ''));
if (empty($user_input)) { exit; }

/* ============================================================
    1.5. DEPARTMENT HELPER FUNCTIONS
============================================================ */

// Get student's department from session
function getStudentDepartment() {
    if (isset($_SESSION['student_department'])) {
        return $_SESSION['student_department'];
    }
    if (isset($_SESSION['department'])) {
        return $_SESSION['department'];
    }
    return null;
}

// Get student's year level from registration number
function getStudentYearLevelFromReg($student_reg) {
    if (preg_match('/\/(\d{4})\//', $student_reg, $matches)) {
        $admission_year = intval($matches[1]);
        $current_year = date('Y');
        $year_diff = $current_year - $admission_year;
        
        if ($year_diff == 0) return 'First Year';
        if ($year_diff == 1) return 'Second Year';
        if ($year_diff == 2) return 'Third Year';
        if ($year_diff >= 3) return 'Fourth Year';
    }
    return 'First Year';
}

// Get units filtered by student's department from academic_workload
function getUnitsByStudentDepartment($conn, $department, $year_level, $semester_num) {
    if (!$department) {
        return [];
    }
    
    $semester_name = ($semester_num == 1) ? '1st Semester' : '2nd Semester';
    
    $query = "SELECT unit_code, unit_name, year_level, semester_level, offering_time 
              FROM academic_workload 
              WHERE department = ? 
              AND year_level = ? 
              AND semester_level = ? 
              ORDER BY unit_code";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sss", $department, $year_level, $semester_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $units = [];
    while ($row = $result->fetch_assoc()) {
        $units[] = $row;
    }
    return $units;
}

// Get timetable filtered by student's department
function getStudentTimetableByDepartment($conn, $department, $year_level, $semester_num) {
    if (!$department) {
        return [];
    }
    
    $query = "SELECT t.unit_code, t.course_title, t.day_of_week, t.time_from, t.time_to, t.venue, t.lecturer 
              FROM timetable t 
              INNER JOIN academic_workload aw ON t.unit_code = aw.unit_code
              WHERE aw.department = ? 
              AND t.year_level = ? 
              AND t.semester = ? 
              ORDER BY FIELD(t.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), t.time_from";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sss", $department, $year_level, $semester_num);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $timetable = [];
    while ($row = $result->fetch_assoc()) {
        $timetable[] = $row;
    }
    return $timetable;
}

// Get all lecturers in a specific department
function getLecturersByDepartment($conn, $department) {
    $query = "SELECT full_name, reg_number, email, phone, department, role 
              FROM users 
              WHERE role = 'lecturer' 
              AND department = ?
              ORDER BY full_name";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $department);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $lecturers = [];
    while ($row = $result->fetch_assoc()) {
        $lecturers[] = $row;
    }
    return $lecturers;
}

/* ============================================================
    NEW FUNCTIONS FOR LECTURER, ASSIGNMENTS & PROGRESS
============================================================ */

// Get lecturer details by name
function getLecturerDetails($conn, $lecturer_name) {
    $query = "SELECT full_name, reg_number, email, phone, department, role 
              FROM users 
              WHERE role = 'lecturer' 
              AND (full_name LIKE ? OR reg_number LIKE ?)";
    $search_term = "%$lecturer_name%";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $search_term, $search_term);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

// Get units taught by a specific lecturer
function getUnitsByLecturer($conn, $lecturer_name) {
    $query = "SELECT DISTINCT unit_code, course_title, year_level, semester, day_of_week, time_from, time_to, venue 
              FROM timetable 
              WHERE lecturer LIKE ? 
              ORDER BY year_level, semester, unit_code";
    $search_term = "%$lecturer_name%";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $search_term);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $units = [];
    while ($row = $result->fetch_assoc()) {
        $units[] = $row;
    }
    return $units;
}

// Get pending assignments for a student - FIXED VERSION
// Only shows assignments that have NOT been submitted yet
function getPendingAssignments($conn, $student_reg) {
    $registered_query = "SELECT DISTINCT unit_code FROM registered_courses WHERE student_reg_no = ?";
    $stmt = $conn->prepare($registered_query);
    $stmt->bind_param("s", $student_reg);
    $stmt->execute();
    $registered_result = $stmt->get_result();
    
    $registered_units = [];
    while ($row = $registered_result->fetch_assoc()) {
        $registered_units[] = $row['unit_code'];
    }
    
    if (empty($registered_units)) {
        return [];
    }
    
    $placeholders = implode(',', array_fill(0, count($registered_units), '?'));
    $types = str_repeat('s', count($registered_units));
    
    $query = "SELECT a.* 
              FROM assignments a 
              WHERE a.unit_code IN ($placeholders) 
              AND a.due_date >= CURDATE()
              AND NOT EXISTS (
                  SELECT 1 FROM assignment_submissions 
                  WHERE assignment_id = a.id AND student_reg = ?
              )
              ORDER BY a.due_date ASC";
    
    $stmt = $conn->prepare($query);
    $params = array_merge($registered_units, [$student_reg]);
    $stmt->bind_param($types . "s", ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $assignments = [];
    while ($row = $result->fetch_assoc()) {
        $assignments[] = $row;
    }
    return $assignments;
}

// Get assignment deadline for a specific unit
function getAssignmentDeadline($conn, $unit_code, $student_reg = null) {
    $query = "SELECT a.*, 
              (SELECT id FROM assignment_submissions 
               WHERE assignment_id = a.id AND student_reg = ? LIMIT 1) as has_submitted
              FROM assignments a 
              WHERE a.unit_code = ? 
              AND a.due_date >= CURDATE()
              ORDER BY a.due_date ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $student_reg, $unit_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $assignments = [];
    while ($row = $result->fetch_assoc()) {
        $assignments[] = $row;
    }
    return $assignments;
}

// Get student's academic progress and performance
function getStudentAcademicProgress($conn, $student_reg) {
    $query = "SELECT asub.*, a.unit_code, a.title, a.total_marks, a.assessment_type
              FROM assignment_submissions asub
              JOIN assignments a ON asub.assignment_id = a.id
              WHERE asub.student_reg = ? 
              AND asub.obtained_marks IS NOT NULL
              ORDER BY a.unit_code, a.due_date";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $student_reg);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $performance = [];
    $total_obtained = 0;
    $total_possible = 0;
    $unit_performance = [];
    
    while ($row = $result->fetch_assoc()) {
        $unit_code = $row['unit_code'];
        $obtained = floatval($row['obtained_marks']);
        $total = floatval($row['total_marks']);
        
        $total_obtained += $obtained;
        $total_possible += $total;
        
        if (!isset($unit_performance[$unit_code])) {
            $unit_performance[$unit_code] = [
                'unit_code' => $unit_code,
                'total_obtained' => 0,
                'total_possible' => 0,
                'assignments' => []
            ];
        }
        
        $unit_performance[$unit_code]['total_obtained'] += $obtained;
        $unit_performance[$unit_code]['total_possible'] += $total;
        $unit_performance[$unit_code]['assignments'][] = [
            'title' => $row['title'],
            'type' => $row['assessment_type'],
            'obtained' => $obtained,
            'total' => $total,
            'percentage' => ($total > 0) ? round(($obtained / $total) * 100, 1) : 0
        ];
    }
    
    $overall_percentage = ($total_possible > 0) ? round(($total_obtained / $total_possible) * 100, 1) : 0;
    
    foreach ($unit_performance as &$unit) {
        $unit['percentage'] = ($unit['total_possible'] > 0) ? 
            round(($unit['total_obtained'] / $unit['total_possible']) * 100, 1) : 0;
    }
    
    return [
        'overall_percentage' => $overall_percentage,
        'total_obtained' => $total_obtained,
        'total_possible' => $total_possible,
        'unit_performance' => $unit_performance,
        'units_count' => count($unit_performance)
    ];
}

// Generate academic advice based on performance
function generateAcademicAdvice($conn, $student_reg, $performance) {
    $advice = [];
    
    $pending = getPendingAssignments($conn, $student_reg);
    if (!empty($pending)) {
        $advice[] = "⚠️ <b>Hey! You have " . count($pending) . " pending assignment(s):</b><br>";
        foreach ($pending as $p) {
            $days_left = ceil((strtotime($p['due_date']) - time()) / (60 * 60 * 24));
            $advice[] = "  • <b>{$p['unit_code']}</b> - '{$p['title']}' due in {$days_left} days ({$p['due_date']})";
        }
        $advice[] = "<br>💡 <i>Don't wait until the last minute! Start early and stay ahead! 😊</i><br>";
    }
    
    if ($performance['overall_percentage'] >= 80) {
        $advice[] = "🌟 <b>Wow! You're crushing it!</b> {$performance['overall_percentage']}% is fantastic!<br>";
        $advice[] = "💡 Keep that momentum going! Maybe help a friend who's struggling? 🤝<br>";
    } elseif ($performance['overall_percentage'] >= 60) {
        $advice[] = "📚 <b>Good job!</b> You're at {$performance['overall_percentage']}%. You're on the right track!<br>";
        $advice[] = "💡 A little more effort and you'll be at the top! Focus on areas below 70%. 💪<br>";
    } elseif ($performance['overall_percentage'] >= 40) {
        $advice[] = "📖 <b>You're making progress!</b> {$performance['overall_percentage']}% shows you're trying.<br>";
        $advice[] = "💡 Consider forming a study group or visiting your lecturers during office hours. You've got this! 🎯<br>";
    } elseif ($performance['units_count'] > 0) {
        $advice[] = "⚠️ <b>Hey, don't give up!</b> Your current performance is at {$performance['overall_percentage']}%.<br>";
        $advice[] = "💡 Talk to your lecturers, join study groups, and create a study schedule. Every expert was once a beginner! 🌱<br>";
    }
    
    $weak_units = [];
    foreach ($performance['unit_performance'] as $unit) {
        if ($unit['percentage'] < 50) {
            $weak_units[] = $unit['unit_code'];
        }
    }
    
    if (!empty($weak_units)) {
        $advice[] = "🎯 <b>Units needing some love:</b> " . implode(', ', $weak_units) . "<br>";
        $advice[] = "💡 Ask your lecturers for extra materials or past papers. Practice makes perfect! 📝<br>";
    }
    
    if (empty($advice)) {
        $advice[] = "📝 Hey there! I notice you haven't submitted many assignments yet.<br>";
        $advice[] = "💡 The journey of a thousand miles begins with a single step. Start with one assignment today! 🚀<br>";
    }
    
    return $advice;
}

function extractLecturerName($input) {
    $patterns = [
        '/who (?:is|are)\s+([a-zA-Z\.\s]+)/i',
        '/tell me about\s+([a-zA-Z\.\s]+)/i',
        '/details? of\s+([a-zA-Z\.\s]+)/i',
        '/lecturer\s+([a-zA-Z\.\s]+)/i',
        '/units? taught by\s+([a-zA-Z\.\s]+)/i',
        '/what units? does\s+([a-zA-Z\.\s]+) teach/i',
        '/what is the email of\s+([a-zA-Z\.\s]+)/i',
        '/email of\s+([a-zA-Z\.\s]+)/i',
        '/phone of\s+([a-zA-Z\.\s]+)/i',
        '/contact of\s+([a-zA-Z\.\s]+)/i',
        '/get (?:email|phone) of\s+([a-zA-Z\.\s]+)/i'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $input, $matches)) {
            $name = trim($matches[1]);
            $name = preg_replace('/\s+(teach|in|at|for|email|phone|contact).*$/i', '', $name);
            $name = preg_replace('/\s+/', ' ', $name);
            return trim($name);
        }
    }
    return null;
}

// Extract unit code from query for deadline check
function extractUnitCodeForDeadline($input) {
    if (preg_match('/([A-Z]{3,4}[0-9]{4})/i', $input, $matches)) {
        return strtoupper($matches[1]);
    }
    return null;
}

// Check if query is about a lecturer (BEFORE vocabulary)
function isLecturerQuery($input) {
    $lecturer_patterns = [
        '/who (?:is|are)/i',
        '/tell me about/i',
        '/details? of/i',
        '/lecturer/i',
        '/units? taught by/i',
        '/what units? does/i',
        '/what is the email of/i',
        '/email of/i',
        '/mr\./i',
        '/mrs\./i',
        '/ms\./i',
        '/dr\./i',
        '/prof\./i',
        '/list lecturers/i',
        '/all lecturers/i',
        '/lecturers in my department/i',
        '/who teaches in my department/i',
        '/department lecturers/i',
        '/show me lecturers/i'
    ];
    
    foreach ($lecturer_patterns as $pattern) {
        if (preg_match($pattern, $input)) {
            return true;
        }
    }
    return false;
}

/* ===============================
    2. DICTIONARY / VOCABULARY FUNCTIONS
================================ */
function getWordDefinition($word, $conn) {
    $word = trim(strtolower(preg_replace('/[^a-zA-Z\s-]/', '', $word)));
    if (strlen($word) < 2) { return null; }
    
    $local_def = getLocalDefinition($word, $conn);
    if ($local_def) { return $local_def; }
    
    $api_def = getAPIDefinition($word);
    if ($api_def) {
        saveToVocabulary($word, $api_def, $conn);
        return $api_def;
    }
    return null;
}

function getLocalDefinition($word, $conn) {
    $stmt = $conn->prepare("SELECT word, part_of_speech, definition, example, synonyms FROM vocabulary WHERE word = ? LIMIT 1");
    $stmt->bind_param("s", $word);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $output = "<b>📚 Definition of \"{$row['word']}\":</b><br><br>";
        $output .= "<b>{$row['part_of_speech']}:</b> {$row['definition']}<br>";
        if ($row['example']) { $output .= "<i>Example: \"{$row['example']}\"</i><br>"; }
        if ($row['synonyms']) { $output .= "<b>Synonyms:</b> {$row['synonyms']}<br>"; }
        return $output;
    }
    return null;
}

function getAPIDefinition($word) {
    $api_url = "https://api.dictionaryapi.dev/api/v2/entries/en/" . urlencode($word);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Academic Assistant Bot/1.0');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200 && $response) {
        $data = json_decode($response, true);
        if (!empty($data) && isset($data[0])) {
            $word_data = $data[0];
            $word_name = $word_data['word'];
            $meanings = $word_data['meanings'];
            $result = "<b>📚 Definition of \"{$word_name}\":</b><br><br>";
            foreach (array_slice($meanings, 0, 2) as $meaning) {
                $part_of_speech = $meaning['partOfSpeech'];
                $result .= "<b>{$part_of_speech}:</b><br>";
                $definitions = array_slice($meaning['definitions'], 0, 2);
                foreach ($definitions as $index => $def) {
                    $result .= "  " . ($index + 1) . ". {$def['definition']}<br>";
                    if (isset($def['example'])) {
                        $result .= "     <i>Example: \"{$def['example']}\"</i><br>";
                    }
                }
                $result .= "<br>";
            }
            if (isset($meanings[0]['synonyms']) && !empty($meanings[0]['synonyms'])) {
                $synonyms = array_slice($meanings[0]['synonyms'], 0, 5);
                if (!empty($synonyms)) {
                    $result .= "<b>Synonyms:</b> " . implode(", ", $synonyms) . "<br>";
                }
            }
            return $result;
        }
    }
    return null;
}

function saveToVocabulary($word, $definition_html, $conn) {
    $plain_definition = strip_tags($definition_html);
    $part_of_speech = 'noun';
    $definition_text = $plain_definition;
    $example = '';
    $synonyms = '';
    
    if (preg_match('/Example: "([^"]+)"/', $plain_definition, $matches)) {
        $example = $matches[1];
        $definition_text = preg_replace('/Example: "[^"]+"/', '', $definition_text);
    }
    if (preg_match('/Synonyms: (.+)/', $plain_definition, $matches)) {
        $synonyms = $matches[1];
        $definition_text = preg_replace('/Synonyms: .+/', '', $definition_text);
    }
    $definition_text = trim(preg_replace('/\s+/', ' ', $definition_text));
    
    $stmt = $conn->prepare("INSERT INTO vocabulary (word, part_of_speech, definition, example, synonyms) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE definition = VALUES(definition)");
    $stmt->bind_param("sssss", $word, $part_of_speech, $definition_text, $example, $synonyms);
    $stmt->execute();
}

function detectVocabularyIntent($input) {
    if (isLecturerQuery($input)) {
        return false;
    }
    
    $vocab_keywords = ['meaning', 'define', 'what does', 'what is', 'definition of', 'vocabulary', 'word', 'means'];
    foreach ($vocab_keywords as $keyword) {
        if (strpos($input, $keyword) !== false) { return true; }
    }
    return false;
}

function extractWordFromQuery($input) {
    $input = preg_replace('/what (is|does|are) /i', '', $input);
    $input = preg_replace('/define|meaning of|definition of|vocabulary|word|means?/i', '', $input);
    $input = trim($input);
    $words = explode(' ', $input);
    if (count($words) > 1) {
        if (strpos($input, 'artificial') !== false && strpos($input, 'intelligence') !== false) { return 'artificial intelligence'; }
        if (strpos($input, 'machine') !== false && strpos($input, 'learning') !== false) { return 'machine learning'; }
        return end($words);
    }
    return $input;
}

/* ============================================================
    3. FUZZY MATCHING FUNCTIONS
============================================================ */
function fuzzyMatch($input, $target, $threshold = 70) {
    $input = strtolower(trim($input));
    $target = strtolower(trim($target));
    if ($input === $target) { return true; }
    similar_text($input, $target, $percent);
    $distance = levenshtein($input, $target);
    $max_len = max(strlen($input), strlen($target));
    $lev_percent = ($max_len > 0) ? (1 - ($distance / $max_len)) * 100 : 0;
    $similarity = max($percent, $lev_percent);
    return $similarity >= $threshold;
}

function getClosestMatch($input, $options, $threshold = 70) {
    $best_match = null;
    $best_score = 0;
    foreach ($options as $option) {
        $input = strtolower(trim($input));
        $option = strtolower(trim($option));
        similar_text($input, $option, $percent);
        $distance = levenshtein($input, $option);
        $max_len = max(strlen($input), strlen($option));
        $lev_percent = ($max_len > 0) ? (1 - ($distance / $max_len)) * 100 : 0;
        $score = max($percent, $lev_percent);
        if ($score > $best_score && $score >= $threshold) {
            $best_score = $score;
            $best_match = $option;
        }
    }
    return $best_match;
}

function fuzzySearchUnits($search_term, $conn, $limit = 5) {
    $query = "SELECT unit_code, unit_name FROM academic_workload";
    $result = mysqli_query($conn, $query);
    $matches = [];
    $search = strtolower(trim($search_term));
    while ($row = mysqli_fetch_assoc($result)) {
        $unit_code = strtolower($row['unit_code']);
        $unit_name = strtolower($row['unit_name']);
        similar_text($search, $unit_code, $code_similarity);
        $code_distance = levenshtein($search, $unit_code);
        $code_max_len = max(strlen($search), strlen($unit_code));
        $code_lev_percent = ($code_max_len > 0) ? (1 - ($code_distance / $code_max_len)) * 100 : 0;
        $code_score = max($code_similarity, $code_lev_percent);
        similar_text($search, $unit_name, $name_similarity);
        $name_distance = levenshtein($search, $unit_name);
        $name_max_len = max(strlen($search), strlen($unit_name));
        $name_lev_percent = ($name_max_len > 0) ? (1 - ($name_distance / $name_max_len)) * 100 : 0;
        $name_score = max($name_similarity, $name_lev_percent);
        $score = max($code_score, $name_score);
        if ($score >= 60) {
            $matches[] = [
                'unit_code' => $row['unit_code'],
                'unit_name' => $row['unit_name'],
                'score' => $score,
                'matched_on' => ($code_score > $name_score) ? 'code' : 'name'
            ];
        }
    }
    usort($matches, function($a, $b) { return $b['score'] <=> $a['score']; });
    return array_slice($matches, 0, $limit);
}

/* ============================================================
    4. UNIT FILTERING & REGISTRATION FUNCTIONS
============================================================ */

function getStudentYearLevel($conn, $student_reg) {
    $query = "SELECT DISTINCT t.year_level 
              FROM registered_courses rc 
              JOIN timetable t ON rc.unit_code = t.unit_code 
              WHERE rc.student_reg_no = ? 
              LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $student_reg);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['year_level'];
    }
    
    return getStudentYearLevelFromReg($student_reg);
}

function getCurrentSemester() {
    $current_month = date('n');
    return ($current_month >= 1 && $current_month <= 6) ? 1 : 2;
}

function getSemesterName($semester_num) {
    return ($semester_num == 1) ? '1st Semester' : '2nd Semester';
}

function getStudentTimetable($conn, $year_level, $semester_num) {
    $query = "SELECT unit_code, course_title, day_of_week, time_from, time_to, venue, lecturer 
              FROM timetable 
              WHERE year_level = ? AND semester = ? 
              ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), time_from";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $year_level, $semester_num);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $timetable = [];
    while ($row = $result->fetch_assoc()) {
        $timetable[] = $row;
    }
    return $timetable;
}

function getUnitTitles($conn, $unit_codes) {
    if (empty($unit_codes)) return [];
    
    $placeholders = implode(',', array_fill(0, count($unit_codes), '?'));
    $types = str_repeat('s', count($unit_codes));
    $query = "SELECT unit_code, course_title FROM timetable WHERE unit_code IN ($placeholders)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$unit_codes);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $titles = [];
    while ($row = $result->fetch_assoc()) {
        $titles[$row['unit_code']] = $row['course_title'];
    }
    return $titles;
}

function getStudentRegisteredUnits($conn, $student_reg, $year_level, $semester_num) {
    $student_department = getStudentDepartment();
    
    if (!$student_department) {
        return ['error' => 'Department not found for this student'];
    }
    
    $semester_name = ($semester_num == 1) ? '1st Semester' : '2nd Semester';
    
    $required_query = "SELECT unit_code, unit_name FROM academic_workload 
                       WHERE department = ? AND year_level = ? AND semester_level = ?";
    $stmt = $conn->prepare($required_query);
    $stmt->bind_param("sss", $student_department, $year_level, $semester_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $required_units = [];
    $unit_names = [];
    while ($row = $result->fetch_assoc()) {
        $required_units[] = $row['unit_code'];
        $unit_names[$row['unit_code']] = $row['unit_name'];
    }
    
    if (empty($required_units)) {
        return ['error' => 'No required units found for your department this semester'];
    }
    
    $placeholders = implode(',', array_fill(0, count($required_units), '?'));
    $types = str_repeat('s', count($required_units));
    
    $query = "SELECT unit_code FROM registered_courses 
              WHERE student_reg_no = ? AND unit_code IN ($placeholders)";
    $stmt = $conn->prepare($query);
    $params = array_merge([$student_reg], $required_units);
    $stmt->bind_param("s" . $types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $registered = [];
    while ($row = $result->fetch_assoc()) {
        $registered[] = $row['unit_code'];
    }
    
    $not_registered = array_diff($required_units, $registered);
    
    $required_with_names = [];
    $registered_with_names = [];
    $not_registered_with_names = [];
    
    foreach ($required_units as $unit) {
        $unit_name = $unit_names[$unit] ?? 'Unit Name Not Found';
        $required_with_names[] = [
            'code' => $unit,
            'name' => $unit_name,
            'full' => $unit . ' - ' . $unit_name
        ];
        
        if (in_array($unit, $registered)) {
            $registered_with_names[] = [
                'code' => $unit,
                'name' => $unit_name,
                'full' => $unit . ' - ' . $unit_name
            ];
        } else {
            $not_registered_with_names[] = [
                'code' => $unit,
                'name' => $unit_name,
                'full' => $unit . ' - ' . $unit_name
            ];
        }
    }
    
    return [
        'required' => $required_units,
        'required_with_names' => $required_with_names,
        'registered' => $registered,
        'registered_with_names' => $registered_with_names,
        'not_registered' => $not_registered,
        'not_registered_with_names' => $not_registered_with_names,
        'total_required' => count($required_units),
        'total_registered' => count($registered),
        'completion_percentage' => count($required_units) > 0 ? (count($registered) / count($required_units)) * 100 : 0
    ];
}

function displayTimetable($timetable, $year_level, $semester_name, $student_reg) {
    if (empty($timetable)) {
        return "<b>⚠️ No timetable found for {$year_level}, {$semester_name}</b><br><br>
                💡 <i>Please contact the academic office.</i>";
    }
    
    $output = "<b>📅 Your {$year_level} - {$semester_name} Timetable</b><br>";
    $output .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br><br>";
    
    $counter = 1;
    foreach ($timetable as $unit) {
        $output .= "<b>{$counter}. {$unit['unit_code']} - {$unit['course_title']}</b><br>";
        $output .= "   📆 <b>Day:</b> " . ($unit['day_of_week'] ?? 'TBA') . "<br>";
        $output .= "   ⏰ <b>Time:</b> " . ($unit['time_from'] ?? 'TBA') . " - " . ($unit['time_to'] ?? 'TBA') . "<br>";
        $output .= "   📍 <b>Venue:</b> " . ($unit['venue'] ?? 'TBA') . "<br>";
        $output .= "   👨‍🏫 <b>Lecturer:</b> " . ($unit['lecturer'] ?? 'TBA') . "<br><br>";
        $counter++;
    }
    
    $output .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br>";
    $output .= "✅ <i>These are the required units for this semester.</i><br><br>";
    
    $download_token = base64_encode($student_reg . '_' . time());
    $download_url = "download_timetable.php?token=" . urlencode($download_token) . "&student=" . urlencode($student_reg);
    
    $output .= "📥 <b>Download Your Timetable:</b><br>";
    $output .= "🔗 <a href='{$download_url}' target='_blank' style='background-color: #4CAF50; color: white; padding: 8px 16px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 10px;'>📄 Download Timetable (PDF/HTML)</a><br><br>";
    
    $output .= "💡 <i>You can also say 'Download my timetable' to get the link again.</i><br>";
    
    return $output;
}

/* ===============================
    5. FUZZY INTENT DETECTION
================================ */
function fuzzyDetectIntent($input) {
    $intent_patterns = [
        'greet' => ['hi', 'hello', 'hey', 'good morning', 'good afternoon', 'good evening'],
        'farewell' => ['bye', 'goodbye', 'see you', 'later', 'ciao', 'farewell'],
        'gratitude' => ['thank', 'thanks', 'appreciate', 'grateful', 'thx'],
        'how_are_you' => ['how are you', 'how do you do', 'how are you doing', 'how is it going', 'whats up'],
        'my_courses' => ['my courses', 'my units', 'registered courses', 'what am i taking', 'enrolled in', 'my classes'],
        'reg_help' => ['how to register', 'register for units', 'registration process', 'how do i register', 'enroll', 'add units'],
        'timetable' => ['timetable', 'schedule', 'class schedule', 'my classes', 'lecture schedule', 'time table', 'show me my timetable', 'my timetable', 'my class schedule', 'show timetable', 'show schedule', 'my schedule', 'class timetable'],
        'download_timetable' => ['download timetable', 'download my timetable', 'get timetable pdf', 'save timetable', 'export timetable', 'pdf timetable', 'download schedule'],
        'view_all' => ['show me', 'view units', 'list units', 'units for', 'courses for', 'first year', 'second year', 'third year', 'fourth year', '1st year', '2nd year', '3rd year', '4th year', 'year 1', 'year 2', 'year 3', 'year 4', 'freshman', 'sophomore', 'junior', 'senior'],
        'exam_info' => ['exam', 'test', 'assessment', 'exam schedule', 'exam date', 'when is exam'],
        'student_info' => ['who am i', 'my details', 'my info', 'my profile', 'my registration number'],
        'search_unit' => ['search unit', 'find unit', 'look up', 'unit details', 'course details', 'tell me about'],
        'ai_check' => ['what can you do', 'help', 'capabilities', 'what do you do', 'features', 'how can you help'],
        'unit_day' => ['when is', 'what day', 'what time', 'schedule for', 'class for', 'taught on'],
        'course_advice' => ['what courses should i take', 'which units should i register', 'advice on courses', 'recommend units', 'suggest courses', 'what to register', 'help me choose units'],
        'unit_registration_count' => ['how many students', 'how many registered', 'enrollment count', 'class size', 'student count', 'enrollment numbers'],
        'what_to_register' => ['what to register', 'which units should i take', 'units to register', 'required units', 'what units do i need', 'units i should take', 'my required units', 'what am i supposed to register', 'what units should i register'],
        'registration_status' => ['registration status', 'my registration status', 'have i registered', 'check my registration', 'registered units', 'what have i registered for', 'am i fully registered', 'missing units'],
        'list_lecturers' => ['list lecturers', 'all lecturers', 'lecturers in my department', 'who teaches in my department', 'department lecturers', 'list all lecturers', 'show me lecturers', 'lecturers list'],
        'lecturer_info' => ['who is', 'tell me about lecturer', 'lecturer details', 'about dr', 'about mr', 'about mrs', 'about ms', 'who teaches', 'lecturer information', 'what is the email of', 'email of'],
        'lecturer_units' => ['units taught by', 'what units does', 'courses taught by', 'classes taught by', 'which units does', 'what does', 'teach'],
        'pending_assignments' => ['pending assignment', 'upcoming assignment', 'assignments due', 'pending cat', 'assignments not submitted', 'do i have any assignment', 'any pending', 'homework pending'],
        'assignment_deadline' => ['deadline for', 'when is assignment due', 'submission date', 'due date', 'assignment deadline', 'cat deadline', 'submit by'],
        'academic_progress' => ['my performance', 'academic progress', 'how am i doing', 'my grades', 'my marks', 'progress report', 'academic standing', 'how is my performance', 'my results'],
        'academic_advice' => ['give me advice', 'study advice', 'how to improve', 'academic advice', 'tips to improve', 'what should i do', 'advice for', 'help me improve'],
        'graduation' => ['when am i finishing', 'when will i finish', 'when do i graduate', 'when do i finish', 'graduation date', 'completion date', 'finishing school', 'complete school', 'when am i completing']
    ];
    
    $input_lower = strtolower(trim($input));
    $best_intent = null;
    $best_score = 0;
    
    foreach ($intent_patterns as $intent => $patterns) {
        foreach ($patterns as $pattern) {
            if (strpos($input_lower, $pattern) !== false) {
                return $intent;
            }
        }
    }
    
    foreach ($intent_patterns as $intent => $patterns) {
        foreach ($patterns as $pattern) {
            if (strlen($pattern) > 5) {
                similar_text($input_lower, $pattern, $similarity);
                if ($similarity > $best_score && $similarity > 70) {
                    $best_score = $similarity;
                    $best_intent = $intent;
                }
            }
        }
    }
    
    if (!$best_intent) {
        $words = explode(' ', $input_lower);
        foreach ($words as $word) {
            foreach ($intent_patterns as $intent => $patterns) {
                foreach ($patterns as $pattern) {
                    if (fuzzyMatch($word, $pattern, 75)) {
                        return $intent;
                    }
                }
            }
        }
    }
    
    return $best_intent;
}

/* ===============================
    6. PRIORITY 1: KNOWLEDGE BASE CHECK
================================ */
$check_kb = $conn->prepare("SELECT verified_answer FROM ai_knowledge_base WHERE student_query LIKE ? LIMIT 1");
$search_term = "%$user_input%";
$check_kb->bind_param("s", $search_term);
$check_kb->execute();
$kb_result = $check_kb->get_result();

if ($kb_result->num_rows > 0) {
    $row = $kb_result->fetch_assoc();
    $reply = $row['verified_answer'];
    
    $stmt_msg = $conn->prepare("INSERT INTO chat_messages (conversation_id, sender_type, message) VALUES (?, 'student', ?)");
    $stmt_msg->bind_param("ss", $sess_id, $user_input);
    $stmt_msg->execute();

    $stmt_bot = $conn->prepare("INSERT INTO chat_messages (conversation_id, sender_type, message) VALUES (?, 'bot', ?)");
    $stmt_bot->bind_param("ss", $sess_id, $reply);
    $stmt_bot->execute();

    echo "<b>Verified Support:</b> " . $reply;
    exit; 
}
/* ===============================
    6.5. ADMISSION & JOIN DATE CHECKS
================================ */

// Check for "when did I join" or "admission date" questions
if (preg_match('/(when did i join|join date|admission date|enrollment date|when was i admitted|when did i start|start date|registration date|when did i enroll)/i', $user_input)) {
    $student_reg = $_SESSION['reg_number'] ?? null;
    
    if (!$student_reg) {
        echo "🔐 Please log in first to see your admission/join date.<br><br>";
        echo "💡 <i>Once logged in, I'll tell you when you joined the university!</i>";
        exit;
    }
    
    // Get admission year from registration number
    $admission_year = null;
    $admission_month = null;
    $admission_semester = null;
    
    if (preg_match('/\/(\d{4})\//', $student_reg, $matches)) {
        $admission_year = intval($matches[1]);
    }
    
    // Also try to get from users table if available
    $query = "SELECT created_at, reg_number FROM users WHERE reg_number = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $student_reg);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_created = null;
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (!empty($user['created_at'])) {
            $user_created = $user['created_at'];
        }
    }
    
    // Determine admission semester based on registration number pattern
    // Format: BBM/2026/00006 - 2026 is admission year
    if ($admission_year) {
        // Kenyan universities typically admit in September
        $admission_month = "September";
        $admission_semester = "1st Semester";
        $admission_period = "September - December";
    }
    
    $current_year = date('Y');
    $current_month = date('n');
    $current_semester_num = getCurrentSemester();
    $student_year = getStudentYearLevel($conn, $student_reg);
    
    // Calculate how long they've been in the university
    $years_attended = $current_year - $admission_year;
    $months_attended = $years_attended * 12;
    
    // Calculate current semester number
    $year_map = ['First Year' => 1, 'Second Year' => 2, 'Third Year' => 3, 'Fourth Year' => 4];
    $current_year_num = $year_map[$student_year] ?? 1;
    $current_semester_number = (($current_year_num - 1) * 2) + $current_semester_num;
    
    echo "<b>📅 Your Admission & Enrollment Information</b><br><br>";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br><br>";
    
    echo "<b>🎓 Student Details:</b><br>";
    echo "   🆔 <b>Registration Number:</b> {$student_reg}<br>";
    
    if ($admission_year) {
        echo "   📅 <b>Admission Year:</b> {$admission_year}<br>";
        echo "   📆 <b>Admission Month:</b> {$admission_month} {$admission_year}<br>";
        echo "   📚 <b>Admission Semester:</b> {$admission_semester} ({$admission_period})<br>";
    }
    
    if ($user_created) {
        echo "   🕐 <b>Account Created:</b> " . date('F j, Y', strtotime($user_created)) . "<br>";
    }
    
    echo "<br><b>⏰ Time at University:</b><br>";
    
    if ($admission_year) {
        $total_months = $current_semester_number * 4;
        $months_completed = ($current_semester_number - 1) * 4;
        
        echo "   🗓️ <b>Enrolled Since:</b> {$admission_month} {$admission_year}<br>";
        echo "   ⏳ <b>Years Attended:</b> " . ($current_year - $admission_year) . " year(s)<br>";
        echo "   📊 <b>Semesters Completed:</b> " . ($current_semester_number - 1) . " semester(s)<br>";
        echo "   📅 <b>Total Months in Program:</b> {$months_completed} months<br>";
        
        // Calculate days approximately
        $admission_timestamp = strtotime("{$admission_year}-09-01");
        $current_timestamp = time();
        $days_diff = floor(($current_timestamp - $admission_timestamp) / (60 * 60 * 24));
        if ($days_diff > 0) {
            echo "   📆 <b>Days Since Joining:</b> Approximately {$days_diff} days<br>";
        }
    }
    
    echo "<br><b>📚 Current Status:</b><br>";
    echo "   🎓 <b>Current Year:</b> {$student_year}<br>";
    echo "   📚 <b>Current Semester:</b> " . getSemesterName($current_semester_num) . "<br>";
    echo "   🔢 <b>Semester Number:</b> {$current_semester_number} of 8<br>";
    
    // Calculate expected graduation
    if ($admission_year) {
        $graduation_year = $admission_year + 3; // Assuming 4-year program
        echo "<br><b>🏁 Expected Graduation:</b> Year {$graduation_year}<br>";
    }
    
    echo "<br>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br>";
    echo "💡 <i>Your registration number format: PROGRAM/YEAR/SEQUENCE (e.g., BBM/2026/00006)</i><br>";
    echo "💡 <i>Say 'How many semesters left?' to see your remaining timeline!</i><br>";
    echo "💡 <i>Say 'When am I finishing?' to see your graduation date!</i>";
    exit;
}

// Check for "how many months have I been here" or similar
if (preg_match('/(how many months|how long have i been|how long have i studied|time at university|duration of study)/i', $user_input)) {
    $student_reg = $_SESSION['reg_number'] ?? null;
    
    if (!$student_reg) {
        echo "🔐 Please log in first to see how long you've been at the university.<br><br>";
        exit;
    }
    
    $admission_year = null;
    if (preg_match('/\/(\d{4})\//', $student_reg, $matches)) {
        $admission_year = intval($matches[1]);
    }
    
    $student_year = getStudentYearLevel($conn, $student_reg);
    $current_semester_num = getCurrentSemester();
    
    $year_map = ['First Year' => 1, 'Second Year' => 2, 'Third Year' => 3, 'Fourth Year' => 4];
    $current_year_num = $year_map[$student_year] ?? 1;
    $current_semester_number = (($current_year_num - 1) * 2) + $current_semester_num;
    
    $total_months_studied = ($current_semester_number - 1) * 4;
    $years_studied = floor($total_months_studied / 12);
    $remaining_months = $total_months_studied % 12;
    
    $current_year = date('Y');
    
    echo "<b>⏰ Your Academic Duration</b><br><br>";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br><br>";
    
    echo "📌 <b>Time Invested in Your Education:</b><br>";
    echo "   🎓 <b>Current Year:</b> {$student_year}<br>";
    echo "   📚 <b>Current Semester:</b> " . getSemesterName($current_semester_num) . "<br>";
    echo "   📅 <b>Total Months Studied:</b> {$total_months_studied} months<br>";
    
    if ($years_studied > 0) {
        echo "   🗓️ <b>That's approximately:</b> {$years_studied} year(s)";
        if ($remaining_months > 0) {
            echo " and {$remaining_months} month(s)";
        }
        echo "<br>";
    }
    
    echo "   📊 <b>Semesters Completed:</b> " . ($current_semester_number - 1) . " semester(s)<br>";
    
    if ($admission_year) {
        $current_year = date('Y');
        $total_years = $current_year - $admission_year;
        echo "   📆 <b>Calendar Years:</b> {$total_years} year(s) since admission<br>";
    }
    
    echo "<br>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br>";
    echo "💡 <i>Each semester is 4 months long (September-December or January-April)</i><br>";
    echo "💡 <i>Say 'When did I join?' to see your full admission details!</i>";
    exit;
}

/* ===============================
    6.6. SEMESTER & GRADUATION CHECKS
================================ */

/* ===============================
    6.6. SEMESTER & GRADUATION CHECKS (continued)
================================ */

// Check for "how many semesters left"
if (preg_match('/(how many semesters|semesters left|semesters remaining|how many more semesters|remaining semesters)/i', $user_input)) {
    $student_reg = $_SESSION['reg_number'] ?? null;
    
    if (!$student_reg) {
        echo "🔐 Please log in first to check your remaining semesters.<br><br>";
        echo "💡 <i>Once logged in, I'll calculate how many semesters you have left!</i>";
        exit;
    }
    
    $student_year = getStudentYearLevel($conn, $student_reg);
    $current_semester_num = getCurrentSemester();
    
    $year_map = [
        'First Year' => 1,
        'Second Year' => 2,
        'Third Year' => 3,
        'Fourth Year' => 4
    ];
    
    $current_year_num = $year_map[$student_year] ?? 1;
    $total_semesters = 8;
    $months_per_semester = 4;
    
    $current_semester_number = (($current_year_num - 1) * 2) + $current_semester_num;
    $semesters_completed = $current_semester_number - 1;
    $semesters_remaining = $total_semesters - $current_semester_number;
    $months_completed = $semesters_completed * $months_per_semester;
    $months_remaining = $semesters_remaining * $months_per_semester;
    $years_remaining = floor($semesters_remaining / 2);
    
    // Get admission year
    $admission_year = null;
    if (preg_match('/\/(\d{4})\//', $student_reg, $matches)) {
        $admission_year = intval($matches[1]);
    }
    
    echo "<b>📊 Your Academic Progress Timeline</b><br><br>";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br>";
    echo "🎓 <b>Current Position:</b> {$student_year}, " . getSemesterName($current_semester_num) . "<br>";
    echo "📊 <b>Total Program Duration:</b> {$total_semesters} semesters (4 years / 32 months)<br>";
    echo "⏳ <b>Current Semester Number:</b> {$current_semester_number} of {$total_semesters}<br>";
    echo "✅ <b>Semesters Completed:</b> {$semesters_completed} semester(s)<br>";
    echo "📅 <b>Months Completed:</b> {$months_completed} months<br>";
    echo "📈 <b>Progress:</b> " . round(($current_semester_number / $total_semesters) * 100, 1) . "% complete<br><br>";
    
    if ($semesters_remaining > 0) {
        echo "<b>⏰ Time Remaining:</b><br>";
        echo "   📅 <b>Semesters Remaining:</b> <b style='color: #4CAF50;'>{$semesters_remaining}</b> semester(s)<br>";
        echo "   ⏰ <b>Months Remaining:</b> Approximately <b>{$months_remaining}</b> months<br>";
        if ($years_remaining > 0) {
            echo "   🗓️ Approximately <b>{$years_remaining}</b> year(s)";
            if ($semesters_remaining % 2 != 0) {
                echo " and <b>1</b> semester";
            }
            echo "<br>";
        }
        
        // Calculate expected graduation
        $current_year = date('Y');
        $graduation_year = $current_year + ceil($semesters_remaining / 2);
        echo "<br>🏁 <b>Expected Graduation:</b> Year {$graduation_year}<br>";
        
        if ($admission_year) {
            $total_duration_years = $graduation_year - $admission_year;
            echo "📚 <b>Total Program Duration:</b> {$total_duration_years} years (from {$admission_year} to {$graduation_year})<br>";
        }
        
        // Add motivational message
        echo "<br>💪 <b>Keep Going!</b> You've already completed " . round(($current_semester_number / $total_semesters) * 100) . "% of your journey!<br>";
    } else {
        echo "🎉 <b>CONGRATULATIONS!</b> You're in your FINAL semester!<br>";
        echo "🏁 Complete your remaining requirements to graduate!<br><br>";
    }
    
    echo "<br>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br>";
    echo "💡 <i>Note: Each semester is 4 months long</i><br>";
    echo "💡 <i>Say 'Which semester am I in?' to see your current semester details!</i><br>";
    echo "💡 <i>Say 'When did I join?' to see your admission details!</i>";
    exit;
}

// Check for "which semester am I in"
if (preg_match('/(which semester|what semester|current semester|am i in which semester|what year am i in)/i', $user_input)) {
    $student_reg = $_SESSION['reg_number'] ?? null;
    
    if (!$student_reg) {
        echo "🔐 Please log in first to see your current semester.<br><br>";
        echo "💡 <i>Once logged in, I'll tell you exactly which semester you're in!</i>";
        exit;
    }
    
    $student_year = getStudentYearLevel($conn, $student_reg);
    $current_semester_num = getCurrentSemester();
    $semester_name = getSemesterName($current_semester_num);
    $current_year = date('Y');
    $current_month = date('F');
    
    // Determine semester period
    if ($current_semester_num == 1) {
        $semester_period = "September - December (4 months)";
        $semester_months = "September, October, November, December";
        $next_semester = "2nd Semester (January - April)";
        $weeks_left = "Approximately 16 weeks remaining in this semester";
    } else {
        $semester_period = "January - April (4 months)";
        $semester_months = "January, February, March, April";
        $next_semester = "1st Semester (September - December)";
        $weeks_left = "Approximately 16 weeks remaining in this semester";
    }
    
    // Calculate which semester number (1-8)
    $year_map = ['First Year' => 1, 'Second Year' => 2, 'Third Year' => 3, 'Fourth Year' => 4];
    $current_year_num = $year_map[$student_year] ?? 1;
    $semester_number = (($current_year_num - 1) * 2) + $current_semester_num;
    
    echo "<b>🎓 Your Current Academic Standing</b><br><br>";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br>";
    echo "📆 <b>Current Year:</b> {$student_year}<br>";
    echo "📚 <b>Current Semester:</b> {$semester_name}<br>";
    echo "🔢 <b>Semester Number:</b> Semester {$semester_number} of 8<br>";
    echo "📅 <b>Semester Period:</b> {$semester_period}<br>";
    echo "📆 <b>Months:</b> {$semester_months}<br>";
    echo "📌 <b>Current Month:</b> {$current_month}<br>";
    echo "⏰ <b>Time Status:</b> {$weeks_left}<br>";
    echo "🎯 <b>Next Semester:</b> {$next_semester}<br>";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br><br>";
    
    echo "<b>📋 What you can do this semester:</b><br>";
    echo "   ✅ Focus on your core units<br>";
    echo "   ✅ Complete all assignments before deadlines<br>";
    echo "   ✅ Prepare for CATs and final exams<br>";
    echo "   ✅ Attend all lectures and practical sessions<br><br>";
    
    echo "💡 <i>Say 'How many semesters left?' to see your remaining semesters!</i><br>";
    echo "💡 <i>Say 'When am I finishing?' to see your graduation timeline!</i><br>";
    echo "💡 <i>Say 'Show my timetable' to see your class schedule!</i>";
    exit;
}

// Check for "when am I finishing" or graduation questions
if (preg_match('/(when am i finishing|when will i finish|when do i graduate|when do i finish|finishing school|complete school|graduation date|when am i completing|graduation timeline)/i', $user_input)) {
    $student_reg = $_SESSION['reg_number'] ?? null;
    
    if (!$student_reg) {
        echo "🎓 I'd love to tell you when you'll finish school, but you need to log in first!<br><br>";
        echo "🔐 Please log in so I can check your academic progress.<br><br>";
        echo "💡 <i>Once logged in, I'll tell you exactly when you'll graduate!</i>";
        exit;
    }
    
    $student_year = getStudentYearLevel($conn, $student_reg);
    $current_semester_num = getCurrentSemester();
    $semester_name = getSemesterName($current_semester_num);
    
    $year_map = ['First Year' => 1, 'Second Year' => 2, 'Third Year' => 3, 'Fourth Year' => 4];
    $current_year_num = $year_map[$student_year] ?? 1;
    $total_semesters = 8;
    
    $current_semester_number = (($current_year_num - 1) * 2) + $current_semester_num;
    $semesters_remaining = $total_semesters - $current_semester_number;
    $months_remaining = $semesters_remaining * 4;
    $years_remaining = floor($semesters_remaining / 2);
    
    $current_year = date('Y');
    $current_month = date('F');
    
    // Get admission year
    $admission_year = null;
    if (preg_match('/\/(\d{4})\//', $student_reg, $matches)) {
        $admission_year = intval($matches[1]);
    }
    
    // Calculate graduation year and month
    if ($current_year_num == 4) {
        if ($current_semester_num == 1) {
            $graduation_month = "December";
            $graduation_year = $current_year;
            $months_to_graduation = 8; // April to December
        } else {
            $graduation_month = "April";
            $graduation_year = $current_year + 1;
            $months_to_graduation = 4;
        }
    } else {
        $graduation_year = $current_year + ceil($semesters_remaining / 2);
        $graduation_month = "December";
        $months_to_graduation = ($semesters_remaining * 4);
    }
    
    if ($current_semester_num == 1) {
        $current_semester_period = "September - December";
        $semester_end_month = "December";
    } else {
        $current_semester_period = "January - April";
        $semester_end_month = "April";
    }
    
    echo "<b>🎓 Your Graduation & Completion Timeline</b><br><br>";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br><br>";
    
    echo "📌 <b>Your Current Status:</b><br>";
    echo "   🎓 Year: <b>{$student_year}</b><br>";
    echo "   📚 Semester: <b>{$semester_name}</b> ({$current_semester_period})<br>";
    echo "   🔢 Semester {$current_semester_number} of {$total_semesters}<br>";
    
    if ($admission_year) {
        echo "   📅 Admission Year: {$admission_year}<br>";
    }
    
    echo "<br>📊 <b>Progress Overview:</b><br>";
    $percent_complete = round(($current_semester_number / $total_semesters) * 100, 1);
    echo "   📈 {$percent_complete}% Complete<br>";
    
    // Progress bar
    $bar_length = 20;
    $filled = round(($percent_complete / 100) * $bar_length);
    $empty = $bar_length - $filled;
    echo "   █" . str_repeat("█", $filled) . str_repeat("░", $empty) . "█<br>";
    
    if ($semesters_remaining <= 0) {
        echo "<br>🎉 <b>CONGRATULATIONS!</b> 🎉<br>";
        echo "You are in your <b>FINAL SEMESTER</b>!<br>";
        echo "🏁 You will complete school in <b>{$graduation_month} {$graduation_year}</b>!<br><br>";
        
        echo "✅ <b>Graduation Checklist:</b><br>";
        echo "   1️⃣ Complete all pending assignments and CATs<br>";
        echo "   2️⃣ Prepare for final exams<br>";
        echo "   3️⃣ Clear any pending fees<br>";
        echo "   4️⃣ Apply for graduation on time<br>";
        echo "   5️⃣ Attend graduation rehearsal<br><br>";
    } else {
        echo "<br>⏰ <b>Time Remaining Until Graduation:</b><br>";
        echo "   📊 <b>{$semesters_remaining}</b> semester(s) remaining<br>";
        echo "   📅 <b>{$months_remaining}</b> months remaining<br>";
        if ($years_remaining > 0) {
            echo "   🗓️ Approximately <b>{$years_remaining}</b> year(s) left<br>";
        }
        echo "   🎯 <b>{$months_to_graduation}</b> months until graduation<br><br>";
        
        echo "🏁 <b>Expected Graduation:</b> <b style='color: #4CAF50;'>{$graduation_month} {$graduation_year}</b><br><br>";
        
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br>";
        echo "📅 <b>Upcoming Milestones:</b><br>";
        echo "   • End of this semester: {$semester_end_month} {$current_year}<br>";
        echo "   • Next semester starts: " . ($current_semester_num == 1 ? "January " . ($current_year + 1) : "September " . $current_year) . "<br>";
        echo "   • Final semester: " . ($current_year_num < 4 ? "Fourth Year, " : "Your current semester") . "<br>";
        echo "   • Graduation: {$graduation_month} {$graduation_year}<br><br>";
    }
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br>";
    echo "💪 <b>Motivation:</b> You're on track! Keep pushing forward! 🎓<br>";
    echo "💡 <i>Say 'How many semesters left?' for a detailed breakdown!</i><br>";
    echo "💡 <i>Say 'Which semester am I in?' to see your current semester details!</i><br>";
    echo "💡 <i>Say 'When did I join?' to see your admission details!</i>";
    exit;
}
/* ===============================
    6.6. CHECK FOR "MY LECTURERS" QUERY
================================ */
if (preg_match('/(my lecturers|my lecture|tell me about my lecturers|show me my lecturers|who are my lecturers|list my lecturers)/i', $user_input)) {
    $student_reg = $_SESSION['reg_number'] ?? null;
    $student_department = getStudentDepartment();
    
    if (!$student_reg) {
        echo "🔐 Hey there! 👋 Please log in first to see your lecturers.<br><br>";
        echo "💡 <i>Once logged in, I'll show you all the lecturers in your department!</i>";
        exit;
    }
    
    if (!$student_department) {
        echo "⚠️ Your department information is missing. Please contact the administrator.<br><br>";
        exit;
    }
    
    $lecturers = getLecturersByDepartment($conn, $student_department);
    
    if (!empty($lecturers)) {
        echo "<b>👨‍🏫 Your Lecturers in the {$student_department} Department</b><br><br>";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br><br>";
        
        $counter = 1;
        foreach ($lecturers as $lecturer) {
            echo "<b>{$counter}. {$lecturer['full_name']}</b><br>";
            echo "   📧 <b>Email:</b> {$lecturer['email']}<br>";
            if (!empty($lecturer['phone'])) {
                echo "   📞 <b>Phone:</b> {$lecturer['phone']}<br>";
            }
            echo "   🆔 <b>Staff ID:</b> {$lecturer['reg_number']}<br>";
            echo "   🏛️ <b>Department:</b> {$lecturer['department']}<br><br>";
            $counter++;
        }
        
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br>";
        echo "📌 <b>Total Faculty Members:</b> " . count($lecturers) . "<br><br>";
        echo "💡 <i>Want specific lecturer details? Try: 'What is Dr. John Smith email?' or 'What units does Mr. African teach?'</i>";
        exit;
    } else {
        echo "📭 No lecturers found in the {$student_department} department.<br><br>";
        echo "💡 <i>Please contact the academic office for assistance.</i>";
        exit;
    }
}

// Handle email/phone queries for specific lecturers
if (preg_match('/(email|phone|contact|what is the (email|phone|contact) of|get (email|phone) of)\s+([a-zA-Z\.\s]+)/i', $user_input, $matches)) {
    $lecturer_name = trim(end($matches));
    $lecturer_name = preg_replace('/\s+(please|thanks|thank you).*$/i', '', $lecturer_name);
    
    $lecturer = getLecturerDetails($conn, $lecturer_name);
    
    if ($lecturer) {
        echo "<b>👨‍🏫 {$lecturer['full_name']}</b><br><br>";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br>";
        echo "📧 <b>Email:</b> {$lecturer['email']}<br>";
        if (!empty($lecturer['phone'])) {
            echo "📞 <b>Phone:</b> {$lecturer['phone']}<br>";
        }
        echo "🏛️ <b>Department:</b> {$lecturer['department']}<br>";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br><br>";
        echo "💡 <i>Say 'What units does {$lecturer['full_name']} teach?' to see their courses!</i>";
        exit;
    } else {
        echo "❌ I couldn't find a lecturer named '{$lecturer_name}' in our system.<br><br>";
        echo "💡 <i>Try 'List my lecturers' to see all lecturers in your department, then ask for specific ones!</i>";
        exit;
    }
}

/* ===============================
    7. PRIORITY 2: LECTURER QUERIES (BEFORE VOCABULARY)
================================ */
if (preg_match('/(list lecturers|all lecturers|lecturers in my department|who teaches in my department|department lecturers|show me lecturers)/i', $user_input)) {
    $student_reg = $_SESSION['reg_number'] ?? null;
    $student_department = getStudentDepartment();
    
    if (!$student_reg) {
        echo "🔐 Hey there! 👋 I'd love to show you the lecturers in your department, but you need to log in first.<br><br>";
        echo "💡 <i>Once you're logged in, I'll be able to tell you exactly who teaches in your department!</i>";
        exit;
    }
    
    if (!$student_department) {
        echo "⚠️ Oops! I can't seem to find your department information. Please contact the administrator to update your profile.<br><br>";
        echo "💡 <i>Your department needs to be set in your profile for me to show you the right lecturers.</i>";
        exit;
    }
    
    $lecturers = getLecturersByDepartment($conn, $student_department);
    
    if (!empty($lecturers)) {
        echo "<b>👨‍🏫 Meet Your Amazing Lecturers in the {$student_department} Department</b><br><br>";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br><br>";
        
        $counter = 1;
        foreach ($lecturers as $lecturer) {
            echo "<b>{$counter}. {$lecturer['full_name']}</b><br>";
            echo "   📧 <b>Email:</b> {$lecturer['email']}<br>";
            if (!empty($lecturer['phone'])) {
                echo "   📞 <b>Phone:</b> {$lecturer['phone']}<br>";
            }
            echo "   🆔 <b>Staff ID:</b> {$lecturer['reg_number']}<br>";
            
            $units = getUnitsByLecturer($conn, $lecturer['full_name']);
            if (!empty($units)) {
                echo "   📚 <b>Teaches:</b> ";
                $unit_list = [];
                foreach ($units as $unit) {
                    $unit_list[] = $unit['unit_code'];
                }
                echo implode(", ", array_slice($unit_list, 0, 5));
                if (count($unit_list) > 5) {
                    echo " + " . (count($unit_list) - 5) . " more";
                }
                echo "<br>";
            }
            echo "<br>";
            $counter++;
        }
        
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br>";
        echo "📌 <b>Total Faculty Members:</b> " . count($lecturers) . "<br><br>";
        echo "💡 <i>Want to know more about a specific lecturer? Try saying 'What units does [lecturer name] teach?'</i><br>";
        echo "💡 <i>Or ask 'Who is [lecturer name]?' to get their full contact details!</i>";
        
        $stmt_bot = $conn->prepare("INSERT INTO chat_messages (conversation_id, sender_type, message) VALUES (?, 'bot', ?)");
        $stmt_bot->bind_param("ss", $sess_id, "List of department lecturers");
        $stmt_bot->execute();
        exit;
    } else {
        echo "📭 Hmm, I couldn't find any lecturers in the {$student_department} department.<br><br>";
        echo "💡 <i>This might be because no lecturers have been assigned yet. Please contact the academic office for assistance.</i>";
        exit;
    }
}

$lecturer_name = extractLecturerName($user_input);
$is_lecturer_query = isLecturerQuery($user_input);

if ($is_lecturer_query && $lecturer_name) {
    $lecturer = getLecturerDetails($conn, $lecturer_name);
    
    if ($lecturer) {
        echo "<b>👨‍🏫 Lecturer Details: {$lecturer['full_name']}</b><br><br>";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br>";
        echo "📧 <b>Email:</b> {$lecturer['email']}<br>";
        if (!empty($lecturer['phone'])) {
            echo "📞 <b>Phone:</b> {$lecturer['phone']}<br>";
        }
        echo "🏛️ <b>Department:</b> {$lecturer['department']}<br>";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br><br>";
        
        $units = getUnitsByLecturer($conn, $lecturer['full_name']);
        
        if (!empty($units)) {
            echo "<b>📚 Units Taught by {$lecturer['full_name']}:</b><br><br>";
            $current_year = '';
            foreach ($units as $unit) {
                if ($unit['year_level'] != $current_year) {
                    $current_year = $unit['year_level'];
                    echo "<b>🎓 {$current_year}:</b><br>";
                }
                echo "  • <b>{$unit['unit_code']}</b> - {$unit['course_title']}<br>";
                echo "    📅 {$unit['semester']} Semester, {$unit['day_of_week']} {$unit['time_from']}-{$unit['time_to']}<br>";
            }
            echo "<br>💡 <i>Say 'Show my timetable' to see when you have these classes!</i>";
        }
        
        $stmt_bot = $conn->prepare("INSERT INTO chat_messages (conversation_id, sender_type, message) VALUES (?, 'bot', ?)");
        $response_summary = "Lecturer details for " . $lecturer['full_name'];
        $stmt_bot->bind_param("ss", $sess_id, $response_summary);
        $stmt_bot->execute();
        exit;
    }
}

if (preg_match('/(units? taught by|courses? taught by|what units? does|what does)\s+([a-zA-Z\.\s]+)/i', $user_input, $matches)) {
    $lecturer_name = trim($matches[2]);
    $lecturer = getLecturerDetails($conn, $lecturer_name);
    
    if ($lecturer) {
        $units = getUnitsByLecturer($conn, $lecturer['full_name']);
        
        if (!empty($units)) {
            echo "<b>📚 Units Taught by {$lecturer['full_name']}</b><br><br>";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br>";
            
            $current_year = '';
            foreach ($units as $unit) {
                if ($unit['year_level'] != $current_year) {
                    $current_year = $unit['year_level'];
                    echo "<br><b>🎓 {$current_year}:</b><br>";
                }
                echo "  • <b>{$unit['unit_code']}</b> - {$unit['course_title']}<br>";
                echo "    📅 {$unit['semester']} Semester, {$unit['day_of_week']} {$unit['time_from']}-{$unit['time_to']}, {$unit['venue']}<br>";
            }
            echo "<br>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br>";
            echo "💡 <i>Want to know more about any of these units? Say 'Tell me about [unit_code]'</i>";
        } else {
            echo "📭 {$lecturer['full_name']} is not currently teaching any active units at the moment.";
        }
        
        $stmt_bot = $conn->prepare("INSERT INTO chat_messages (conversation_id, sender_type, message) VALUES (?, 'bot', ?)");
        $stmt_bot->bind_param("ss", $sess_id, "Lecturer units response");
        $stmt_bot->execute();
        exit;
    }
}

/* ===============================
    8. PRIORITY 3: VOCABULARY CHECK
================================ */
$vocabulary_check = detectVocabularyIntent($user_input);

if ($vocabulary_check) {
    $word_to_define = extractWordFromQuery($user_input);
    $definition = getWordDefinition($word_to_define, $conn);
    
    if ($definition) {
        echo $definition;
        $followups = [
            "📖 Want me to explain another word? I love expanding vocabulary!",
            "💡 Would you like to see how to use this word in a sentence?",
            "🎓 Need help with any other academic vocabulary? I'm here for you!",
            "📚 I can help you build your vocabulary! Just ask me about any word."
        ];
        echo "<br><br><i>" . getRandomResponse($followups) . "</i>";
        
        $stmt_bot = $conn->prepare("INSERT INTO chat_messages (conversation_id, sender_type, message) VALUES (?, 'bot', ?)");
        $definition_short = substr($definition, 0, 500);
        $stmt_bot->bind_param("ss", $sess_id, $definition_short);
        $stmt_bot->execute();
        exit;
    } else {
        echo "🤔 Hmm, I'm still learning the word '{$word_to_define}'! My vocabulary is growing every day.<br><br>";
        echo "💡 <i>Tip: Try asking about academic terms like 'algorithm', 'database', 'semester', or 'curriculum' — those are my specialties!</i><br><br>";
        echo "📝 I've noted this word and will add it to my vocabulary soon! Thanks for helping me learn. 🙏";
        
        $requested_by = $_SESSION['user_name'] ?? 'Guest';
        $stmt = $conn->prepare("INSERT INTO vocabulary_requests (word, requested_by) VALUES (?, ?)");
        $stmt->bind_param("ss", $word_to_define, $requested_by);
        $stmt->execute();
        exit;
    }
}

/* ===============================
    9. SOCIAL INTENT DETECTION
================================ */
function detectSocialIntent($input) {
    $gratitude = ['thank', 'thanks', 'thnx', 'thank you', 'appreciate', 'grateful', 'thx'];
    foreach ($gratitude as $word) {
        if (strpos($input, $word) !== false) { return 'gratitude'; }
    }
    $apologies = ['sorry', 'apologize', 'my bad', 'apologies'];
    foreach ($apologies as $word) {
        if (strpos($input, $word) !== false) { return 'apology'; }
    }
    $greetings = ['hi', 'hello', 'hey', 'good morning', 'good afternoon', 'good evening', 'howdy', 'yo'];
    foreach ($greetings as $word) {
        if (strpos($input, $word) !== false) { return 'greet'; }
    }
    $farewell = ['bye', 'goodbye', 'see you', 'later', 'ciao', 'farewell', 'talk to you later'];
    foreach ($farewell as $word) {
        if (strpos($input, $word) !== false) { return 'farewell'; }
    }
    $how_are_you = ['how are you', 'how are you doing', 'how\'s it going', 'how are things', 'what\'s up', 'sup'];
    foreach ($how_are_you as $phrase) {
        if (strpos($input, $phrase) !== false) { return 'how_are_you'; }
    }
    $compliments = ['you are great', 'you\'re awesome', 'good bot', 'nice', 'cool', 'amazing'];
    foreach ($compliments as $word) {
        if (strpos($input, $word) !== false) { return 'compliment'; }
    }
    return null;
}

/* ===============================
    10. ACADEMIC INTENT DETECTION
================================ */
function detectIntent($input) {
    $fuzzy_intent = fuzzyDetectIntent($input);
    if ($fuzzy_intent) {
        switch ($fuzzy_intent) {
            case 'reg_help': return ['intent' => 'reg_help'];
            case 'my_courses': return ['intent' => 'my_courses'];
            case 'timetable': return ['intent' => 'timetable'];
            case 'download_timetable': return ['intent' => 'download_timetable'];
            case 'exam_info': return ['intent' => 'exam_info'];
            case 'student_info': return ['intent' => 'student_info'];
            case 'ai_check': return ['intent' => 'ai_check'];
            case 'greet': return ['intent' => 'greet'];
            case 'farewell': return ['intent' => 'farewell'];
            case 'gratitude': return ['intent' => 'gratitude'];
            case 'how_are_you': return ['intent' => 'how_are_you'];
            case 'course_advice': return ['intent' => 'course_advice'];
            case 'what_to_register': return ['intent' => 'what_to_register'];
            case 'registration_status': return ['intent' => 'registration_status'];
            case 'list_lecturers': return ['intent' => 'list_lecturers'];
            case 'pending_assignments': return ['intent' => 'pending_assignments'];
            case 'assignment_deadline':
                $unit_code = extractUnitCodeForDeadline($input);
                return ['intent' => 'assignment_deadline', 'unit_code' => $unit_code];
            case 'academic_progress': return ['intent' => 'academic_progress'];
            case 'academic_advice': return ['intent' => 'academic_advice'];
            case 'graduation': return ['intent' => 'graduation'];
            case 'unit_registration_count':
                if (preg_match('/([A-Z]{3,4}[0-9]{4})/i', $input, $matches)) {
                    return ['intent' => 'unit_registration_count', 'unit_code' => strtoupper($matches[1])];
                }
                return ['intent' => 'unit_registration_count'];
            case 'unit_day':
                if (preg_match('/([A-Z]{3,4}[0-9]{4})/i', $input, $matches)) {
                    return ['intent' => 'unit_day', 'unit_code' => strtoupper($matches[1])];
                }
                return ['intent' => 'unit_day'];
            case 'view_all':
                $year_patterns = [
                    'first' => 'First Year', '1st' => 'First Year', 'freshman' => 'First Year', 'year 1' => 'First Year',
                    'second' => 'Second Year', '2nd' => 'Second Year', 'sophomore' => 'Second Year', 'year 2' => 'Second Year',
                    'third' => 'Third Year', '3rd' => 'Third Year', 'junior' => 'Third Year', 'year 3' => 'Third Year',
                    'fourth' => 'Fourth Year', '4th' => 'Fourth Year', 'senior' => 'Fourth Year', 'year 4' => 'Fourth Year'
                ];
                foreach ($year_patterns as $pattern => $year) {
                    if (strpos($input, $pattern) !== false) {
                        return ['intent' => 'view_all', 'year' => $year];
                    }
                }
                return ['intent' => 'view_all'];
        }
    }
    
    $pending_keywords = ['pending assignment', 'upcoming assignment', 'assignments due', 'pending cat', 'do i have any assignment', 'any pending'];
    foreach ($pending_keywords as $keyword) {
        if (strpos($input, $keyword) !== false || fuzzyMatch($input, $keyword, 70)) {
            return ['intent' => 'pending_assignments'];
        }
    }
    
    if ((strpos($input, 'deadline') !== false || strpos($input, 'due') !== false) && preg_match('/([A-Z]{3,4}[0-9]{4})/i', $input, $matches)) {
        return ['intent' => 'assignment_deadline', 'unit_code' => strtoupper($matches[1])];
    }
    
    $progress_keywords = ['my performance', 'academic progress', 'how am i doing', 'my grades', 'my marks', 'progress report', 'academic standing'];
    foreach ($progress_keywords as $keyword) {
        if (strpos($input, $keyword) !== false || fuzzyMatch($input, $keyword, 70)) {
            return ['intent' => 'academic_progress'];
        }
    }
    
    $advice_keywords = ['give me advice', 'study advice', 'how to improve', 'academic advice', 'tips to improve', 'what should i do'];
    foreach ($advice_keywords as $keyword) {
        if (strpos($input, $keyword) !== false || fuzzyMatch($input, $keyword, 70)) {
            return ['intent' => 'academic_advice'];
        }
    }
    
    $graduation_keywords = ['when am i finishing', 'when will i finish', 'when do i graduate', 'finishing school', 'complete school'];
    foreach ($graduation_keywords as $keyword) {
        if (strpos($input, $keyword) !== false || fuzzyMatch($input, $keyword, 70)) {
            return ['intent' => 'graduation'];
        }
    }
    
    if (preg_match('/(tell me about|details about|info about|what is|describe|more about)/i', $input) && preg_match('/[A-Z]{3,4}[0-9]{4}/i', $input, $matches)) {
        return ['intent' => 'unit_details', 'unit_code' => strtoupper($matches[0])];
    }
    
    $what_to_register_keywords = ['what to register', 'which units', 'units to register', 'required units', 'what units do i need', 'register this semester', 'what am i supposed to register', 'what units should i register'];
    foreach ($what_to_register_keywords as $keyword) {
        if (strpos($input, $keyword) !== false || fuzzyMatch($input, $keyword, 70)) {
            return ['intent' => 'what_to_register'];
        }
    }
    
    $status_keywords = ['registration status', 'have i registered', 'check my registration', 'what have i registered for', 'am i fully registered', 'missing units'];
    foreach ($status_keywords as $keyword) {
        if (strpos($input, $keyword) !== false || fuzzyMatch($input, $keyword, 70)) {
            return ['intent' => 'registration_status'];
        }
    }
    
    $timetable_keywords = ['timetable', 'schedule', 'class schedule', 'my classes', 'lecture schedule', 'time table', 'show me my timetable', 'my timetable', 'my class schedule', 'show timetable', 'show schedule', 'my schedule', 'class timetable'];
    foreach ($timetable_keywords as $keyword) {
        if (strpos($input, $keyword) !== false || fuzzyMatch($input, $keyword, 70)) {
            return ['intent' => 'timetable'];
        }
    }
    
    $download_keywords = ['download timetable', 'download my timetable', 'get timetable pdf', 'save timetable', 'export timetable', 'pdf timetable', 'download schedule'];
    foreach ($download_keywords as $keyword) {
        if (strpos($input, $keyword) !== false || fuzzyMatch($input, $keyword, 70)) {
            return ['intent' => 'download_timetable'];
        }
    }
    
    $reg_keywords = ['how to register', 'register for units', 'registration process', 'how do i register', 'enroll', 'add units'];
    foreach ($reg_keywords as $keyword) {
        if (strpos($input, $keyword) !== false || fuzzyMatch($input, $keyword, 70)) {
            return ['intent' => 'reg_help'];
        }
    }
    
    $year_patterns = [
        '/first\s*year|1st\s*year|year\s*1|freshman|^first$|^1st$/' => 'First Year',
        '/second\s*year|2nd\s*year|year\s*2|sophomore|^second$|^2nd$/' => 'Second Year',
        '/third\s*year|3rd\s*year|year\s*3|junior|^third$|^3rd$/' => 'Third Year',
        '/fourth\s*year|4th\s*year|year\s*4|senior|^fourth$|^4th$/' => 'Fourth Year'
    ];
    foreach ($year_patterns as $pattern => $year) {
        if (preg_match($pattern, strtolower($input))) {
            return ['intent' => 'view_all', 'year' => $year];
        }
    }
    
    if (preg_match('/unit|course|what is|tell me about/i', $input) && preg_match('/[A-Z]{3,4}[0-9]{4}/i', $input)) {
        return ['intent' => 'search_unit'];
    }
    
    return null;
}

$social_intent = detectSocialIntent($user_input);
if ($social_intent) {
    $intent = $social_intent;
} else {
    $priority_check = detectIntent($user_input);
    if ($priority_check) {
        $intent = $priority_check['intent'];
        if (isset($priority_check['year'])) { $GLOBALS['target_year'] = $priority_check['year']; }
        if (isset($priority_check['semester'])) { $GLOBALS['target_semester'] = $priority_check['semester']; }
        if (isset($priority_check['unit_code'])) { $GLOBALS['target_unit'] = $priority_check['unit_code']; }
    } else {
        $samples = ['hi', 'hello', 'hey', 'good morning', 'units', 'workload', 'math', 'programming', 'database', 'registration', 'register', 'enroll', 'ai', 'artificial intelligence'];
        $labels = ['greet', 'greet', 'greet', 'greet', 'view_all', 'view_all', 'search_unit', 'search_unit', 'search_unit', 'reg_help', 'reg_help', 'reg_help', 'ai_check', 'ai_check'];
        $vectorizer = new TokenCountVectorizer(new WhitespaceTokenizer());
        $vectorizer->fit($samples);
        $vectorizer->transform($samples);
        $transformer = new TfIdfTransformer($samples);
        $transformer->transform($samples);
        $classifier = new NaiveBayes();
        $classifier->train($samples, $labels);
        $test_sample = [$user_input];
        $vectorizer->transform($test_sample);
        $transformer->transform($test_sample);
        $intent = $classifier->predict($test_sample)[0];
    }
}

/* ===============================
    11. HELPER FUNCTIONS
================================ */
function formatResponse($data, $title) {
    if (empty($data)) { return "No information found for your query."; }
    $response = "<b>$title</b><br>";
    foreach ($data as $item) { $response .= "• " . implode(" - ", $item) . "<br>"; }
    return $response;
}

function getRandomResponse($responses) {
    return $responses[array_rand($responses)];
}

function getCreativeUnknownResponse() {
    $unknown_responses = [
        "🤔 Hmm, that's an interesting question! I'm not very knowledgeable about that specific topic. You might want to reach out to your Head of Department or Academic Advisor for more accurate information. They'd be happy to help! 📚",
        "😅 Oops! I haven't learned about that yet. My expertise is mainly around courses, timetables, assignments, and lecturers. For your question, I'd recommend contacting the HOD or checking the academic office. 🙏",
        "💭 I wish I knew the answer to that! Unfortunately, that's outside my current knowledge base. The best person to ask would be your HOD or course coordinator. They're the real experts! 🎓",
        "🧠 I'm still learning new things every day, but this one stumps me! For the most accurate answer, please consult your Head of Department or academic advisor. They'll sort you out! ✨",
        "🌟 Great question! But I'm afraid it's beyond what I can help with right now. Your HOD or faculty office would be better equipped to answer this. Keep asking great questions though! 💪"
    ];
    return $unknown_responses[array_rand($unknown_responses)];
}

/* ===============================
    12. SOCIAL RESPONSES
================================ */
$social_responses = [
    'gratitude' => ["You're very welcome! 😊 Happy to help!", "My pleasure! Is there anything else you'd like to know?", "Anytime! That's what I'm here for. 👍", "Glad I could help! 😊 Feel free to ask me anything else!"],
    'apology' => ["No worries at all! How can I help you?", "It's all good! What would you like to know?", "No need to apologize! I'm here to help.", "Don't sweat it! What can I do for you? 🤝"],
    'how_are_you' => ["I'm doing great, thank you for asking! 😊 Ready to help you with your academic questions!", "I'm functioning perfectly! 😄 What can I do for you today?", "All systems operational and happy to chat! How about you?", "Living the digital dream! ☁️ How can I assist you today?"],
    'compliment' => ["Aww, thank you! 😊 You're pretty awesome yourself!", "You just made my circuits happy! 🥰 How can I help you?", "Thanks! I try my best to be helpful.", "You're too kind! 🙈 What can I do for you today?"],
    'farewell' => ["Goodbye! 👋 Feel free to come back if you have more questions!", "Take care! Wishing you success in your studies! 🎓", "See you later! Have a great day! 😊", "Bye for now! Remember, I'm always here when you need me! 🌟"]
];

/* ===============================
    13. MAIN ACTION LOGIC
================================ */
switch ($intent) {
    case 'greet':
        $student_name = $_SESSION['user_name'] ?? '';
        $greetings = [
            "Hey there! 👋 I'm your academic assistant. What can I help you with today?",
            "Hello! 😊 Ready to help you with your courses, timetable, or exams!",
            "Hi! Great to see you! What would you like to know about your academic journey?",
            "Hey! 👋 Ask me about your timetable, assignments, lecturers, or academic progress!"
        ];
        if ($student_name) { echo "Hey $student_name! " . getRandomResponse($greetings); }
        else { echo getRandomResponse($greetings); }
        break;
    
    case 'gratitude': echo getRandomResponse($social_responses['gratitude']); break;
    case 'apology': echo getRandomResponse($social_responses['apology']); break;
    case 'how_are_you': echo getRandomResponse($social_responses['how_are_you']); break;
    case 'compliment': echo getRandomResponse($social_responses['compliment']); break;
    case 'farewell': echo getRandomResponse($social_responses['farewell']); break;
    
    case 'graduation':
        $student_reg = $_SESSION['reg_number'] ?? null;
        
        if (!$student_reg) {
            echo "🎓 I'd love to tell you when you'll finish school, but you need to log in first!<br><br>";
            echo "🔐 Please log in so I can check your academic progress.<br><br>";
            echo "💡 <i>Once logged in, I'll tell you exactly when you'll graduate!</i>";
            break;
        }
        
        $student_year = getStudentYearLevel($conn, $student_reg);
        $current_semester_num = getCurrentSemester();
        $semester_name = getSemesterName($current_semester_num);
        
        $year_map = ['First Year' => 1, 'Second Year' => 2, 'Third Year' => 3, 'Fourth Year' => 4];
        $current_year_num = $year_map[$student_year] ?? 1;
        $total_semesters = 8;
        $months_per_semester = 4;
        
        $current_semester_number = (($current_year_num - 1) * 2) + $current_semester_num;
        $semesters_remaining = $total_semesters - $current_semester_number;
        $months_remaining = $semesters_remaining * $months_per_semester;
        $years_remaining = floor($semesters_remaining / 2);
        
        $current_year = date('Y');
        if ($current_year_num == 4) {
            $graduation_season = ($current_semester_num == 1) ? "December " . $current_year : "April " . ($current_year + 1);
        } else {
            $graduation_year = $current_year + $years_remaining + ($current_semester_num == 2 ? 1 : 0);
            $graduation_season = "December " . $graduation_year;
        }
        
        $current_semester_period = ($current_semester_num == 1) ? "September - December" : "January - April";
        
        echo "<b>🎓 Your Graduation Timeline</b><br><br>";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br><br>";
        echo "📌 <b>Your Current Status:</b><br>";
        echo "   🎓 Year: <b>{$student_year}</b><br>";
        echo "   📚 Semester: <b>{$semester_name}</b> ({$current_semester_period})<br><br>";
        
        if ($semesters_remaining <= 0) {
            echo "🎉 <b>CONGRATULATIONS!</b> You are in your FINAL semester!<br>";
            echo "🏁 You will complete school in {$graduation_season}!<br><br>";
        } else {
            echo "⏰ <b>Time Remaining:</b><br>";
            echo "   📊 <b>{$semesters_remaining}</b> semester(s) remaining<br>";
            echo "   📅 <b>{$months_remaining}</b> months remaining<br>";
            if ($years_remaining > 0) echo "   🗓️ Approximately <b>{$years_remaining}</b> year(s) left<br><br>";
            echo "🏁 <b>Expected Completion:</b> <b style='color: #4CAF50;'>{$graduation_season}</b><br><br>";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br>";
            echo "📅 <b>Academic Calendar (4 months per semester):</b><br>";
            echo "   • 1st Semester: September - December<br>";
            echo "   • 2nd Semester: January - April<br>";
            echo "   • Holiday Break: May - August<br><br>";
            $percent_complete = round(($current_semester_number / $total_semesters) * 100, 1);
            echo "📊 <b>Progress:</b> {$percent_complete}% complete<br><br>";
        }
        echo "💡 <i>Say 'How many semesters left?' to see a detailed breakdown!</i>";
        break;
    
    case 'list_lecturers':
        $student_reg = $_SESSION['reg_number'] ?? null;
        $student_department = getStudentDepartment();
        
        if (!$student_reg) {
            echo "🔐 Hey there! 👋 I'd love to show you the lecturers in your department, but you need to log in first.<br><br>";
            echo "💡 <i>Once you're logged in, I'll be able to tell you exactly who teaches in your department!</i>";
            break;
        }
        
        if (!$student_department) {
            echo "⚠️ Oops! I can't seem to find your department information. Please contact the administrator to update your profile.<br><br>";
            echo "💡 <i>Your department needs to be set in your profile for me to show you the right lecturers.</i>";
            break;
        }
        
        $lecturers = getLecturersByDepartment($conn, $student_department);
        
        if (!empty($lecturers)) {
            echo "<b>👨‍🏫 Meet Your Amazing Lecturers in the {$student_department} Department</b><br><br>";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br><br>";
            
            $counter = 1;
            foreach ($lecturers as $lecturer) {
                echo "<b>{$counter}. {$lecturer['full_name']}</b><br>";
                echo "   📧 <b>Email:</b> {$lecturer['email']}<br>";
                if (!empty($lecturer['phone'])) {
                    echo "   📞 <b>Phone:</b> {$lecturer['phone']}<br>";
                }
                echo "   🆔 <b>Staff ID:</b> {$lecturer['reg_number']}<br>";
                
                $units = getUnitsByLecturer($conn, $lecturer['full_name']);
                if (!empty($units)) {
                    echo "   📚 <b>Teaches:</b> ";
                    $unit_list = [];
                    foreach ($units as $unit) {
                        $unit_list[] = $unit['unit_code'];
                    }
                    echo implode(", ", array_slice($unit_list, 0, 5));
                    if (count($unit_list) > 5) {
                        echo " + " . (count($unit_list) - 5) . " more";
                    }
                    echo "<br>";
                }
                echo "<br>";
                $counter++;
            }
            
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br>";
            echo "📌 <b>Total Faculty Members:</b> " . count($lecturers) . "<br><br>";
            echo "💡 <i>Want to know more about a specific lecturer? Try saying 'What units does [lecturer name] teach?'</i><br>";
            echo "💡 <i>Or ask 'Who is [lecturer name]?' to get their full contact details!</i>";
        } else {
            echo "📭 Hmm, I couldn't find any lecturers in the {$student_department} department.<br><br>";
            echo "💡 <i>This might be because no lecturers have been assigned yet. Please contact the academic office for assistance.</i>";
        }
        break;
    
    case 'pending_assignments':
        $student_reg = $_SESSION['reg_number'] ?? null;
        
        if (!$student_reg) {
            echo "🔐 Hey! 👋 I need you to log in first so I can check your pending assignments.<br><br>";
            echo "💡 <i>Once you're logged in, I'll show you all your upcoming deadlines!</i>";
            break;
        }
        
        $pending = getPendingAssignments($conn, $student_reg);
        
        if (!empty($pending)) {
            echo "<b>📋 Your Pending Assignments & CATs</b><br><br>";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br>";
            
            $today = date('Y-m-d');
            foreach ($pending as $assignment) {
                $days_left = ceil((strtotime($assignment['due_date']) - strtotime($today)) / (60 * 60 * 24));
                $type_icon = ($assignment['assessment_type'] == 'CAT') ? '📝' : (($assignment['assessment_type'] == 'Assignment') ? '📄' : '📖');
                $urgency = ($days_left <= 3) ? '🔴 URGENT - Submit ASAP!' : (($days_left <= 7) ? '🟡 Coming soon' : '🟢 You have time');
                
                echo "<b>{$type_icon} {$assignment['assessment_type']}: {$assignment['title']}</b><br>";
                echo "   📚 <b>Unit:</b> {$assignment['unit_code']}<br>";
                echo "   📅 <b>Due Date:</b> " . date('F j, Y', strtotime($assignment['due_date'])) . "<br>";
                echo "   ⏰ <b>Days Left:</b> {$days_left} days — {$urgency}<br>";
                echo "   📊 <b>Total Marks:</b> {$assignment['total_marks']}<br>";
                if (!empty($assignment['description'])) {
                    $desc_short = (strlen($assignment['description']) > 100) ? substr($assignment['description'], 0, 100) . '...' : $assignment['description'];
                    echo "   📝 <b>Description:</b> {$desc_short}<br>";
                }
                echo "<br>";
            }
            
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br>";
            echo "💡 <i>Don't procrastinate! Start early and you'll thank yourself later. 😊</i><br>";
            echo "💡 <i>Say 'My academic progress' to see how you're doing on completed assignments!</i>";
        } else {
            echo "🎉 <b>Great news!</b> You have no pending assignments or CATs!<br><br>";
            echo "✅ All caught up! Time to review your notes or help a friend.<br>";
            echo "💡 <i>Use this free time to prepare for upcoming exams or get ahead on readings! 📖</i>";
        }
        break;
    
    case 'assignment_deadline':
        $unit_code = $GLOBALS['target_unit'] ?? '';
        $student_reg = $_SESSION['reg_number'] ?? null;
        
        if (empty($unit_code)) {
            echo "❓ Hmm, which unit's deadline are you curious about? Try saying 'When is BBM2103 assignment due?'";
            break;
        }
        
        if (!$student_reg) {
            echo "🔐 Please log in first so I can check deadlines for your registered units.<br><br>";
            echo "💡 <i>Once logged in, I'll show you exactly when your assignments are due!</i>";
            break;
        }
        
        $assignments = getAssignmentDeadline($conn, $unit_code, $student_reg);
        
        if (!empty($assignments)) {
            echo "<b>📅 Assignment Deadlines for {$unit_code}</b><br><br>";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br>";
            
            $today = date('Y-m-d');
            foreach ($assignments as $assignment) {
                $days_left = ceil((strtotime($assignment['due_date']) - strtotime($today)) / (60 * 60 * 24));
                $type_icon = ($assignment['assessment_type'] == 'CAT') ? '📝' : (($assignment['assessment_type'] == 'Assignment') ? '📄' : '📖');
                $urgency_msg = ($days_left <= 3) ? "⏰ Hurry! This is due very soon!" : (($days_left <= 7) ? "📅 You've got about a week." : "✅ Plenty of time, but don't wait too long!");
                
                echo "<b>{$type_icon} {$assignment['assessment_type']}: {$assignment['title']}</b><br>";
                echo "   📅 <b>Due Date:</b> " . date('F j, Y', strtotime($assignment['due_date'])) . "<br>";
                echo "   ⏰ <b>Days Remaining:</b> {$days_left} days — {$urgency_msg}<br>";
                echo "   📊 <b>Total Marks:</b> {$assignment['total_marks']}<br>";
                if (!empty($assignment['description'])) {
                    echo "   📝 <b>Description:</b> {$assignment['description']}<br>";
                }
                echo "<br>";
            }
            
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br>";
            echo "💡 <i>Submit before the deadline to avoid penalties! You've got this! 💪</i>";
        } else {
            $unit_check = $conn->prepare("SELECT unit_name FROM academic_workload WHERE unit_code = ?");
            $unit_check->bind_param("s", $unit_code);
            $unit_check->execute();
            $unit_result = $unit_check->get_result();
            
            if ($unit_result->num_rows > 0) {
                echo "📭 No pending assignments found for {$unit_code}. Either you've submitted everything, or no assignments have been posted yet.<br><br>";
                echo "💡 <i>Say 'Pending assignments' to see all your upcoming work across all units!</i>";
            } else {
                echo "❌ Hmm, I couldn't find a unit with code '{$unit_code}' in our system.<br><br>";
                echo "💡 <i>Double-check the unit code and try again. Example: 'When is BBM2103 CAT due?'</i>";
            }
        }
        break;
    
    case 'academic_progress':
        $student_reg = $_SESSION['reg_number'] ?? null;
        
        if (!$student_reg) {
            echo "🔐 Hey! 👋 Log in first so I can check your academic progress and show you how you're doing.<br><br>";
            echo "💡 <i>Once logged in, I'll give you a full performance report!</i>";
            break;
        }
        
        $progress = getStudentAcademicProgress($conn, $student_reg);
        
        if ($progress['units_count'] > 0) {
            echo "<b>📊 Your Academic Progress Report</b><br><br>";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br>";
            echo "<b>Overall Performance:</b><br>";
            echo "   📈 <b>Total Score:</b> {$progress['total_obtained']} / {$progress['total_possible']} marks<br>";
            echo "   🎯 <b>Overall Percentage:</b> <b>" . $progress['overall_percentage'] . "%</b><br>";
            
            if ($progress['overall_percentage'] >= 80) {
                echo "   🏆 <b>Grade: A (Excellent!)</b> — Keep shining! 🌟<br>";
            } elseif ($progress['overall_percentage'] >= 70) {
                echo "   🌟 <b>Grade: B (Very Good!)</b> — You're doing great!<br>";
            } elseif ($progress['overall_percentage'] >= 60) {
                echo "   📘 <b>Grade: C (Good)</b> — Solid work, keep pushing!<br>";
            } elseif ($progress['overall_percentage'] >= 50) {
                echo "   📙 <b>Grade: D (Satisfactory)</b> — You're passing, but there's room to grow.<br>";
            } else {
                echo "   ⚠️ <b>Grade: E (Needs Improvement)</b> — Don't worry, you can turn this around! 💪<br>";
            }
            
            echo "<br>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br>";
            echo "<b>📚 Unit-by-Unit Breakdown:</b><br><br>";
            
            foreach ($progress['unit_performance'] as $unit_code => $data) {
                $percentage = $data['percentage'];
                if ($percentage >= 80) {
                    $emoji = "🏆";
                } elseif ($percentage >= 70) {
                    $emoji = "✅";
                } elseif ($percentage >= 60) {
                    $emoji = "📘";
                } elseif ($percentage >= 50) {
                    $emoji = "⚠️";
                } else {
                    $emoji = "❌";
                }
                
                echo "<b>{$emoji} {$unit_code}:</b> {$data['total_obtained']}/{$data['total_possible']} marks ({$percentage}%)<br>";
                
                foreach ($data['assignments'] as $assign) {
                    $assign_emoji = ($assign['percentage'] >= 70) ? "✅" : (($assign['percentage'] >= 50) ? "📝" : "⚠️");
                    echo "   {$assign_emoji} {$assign['type']}: {$assign['obtained']}/{$assign['total']} ({$assign['percentage']}%)<br>";
                }
                echo "<br>";
            }
            
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br>";
            echo "💡 <i>Say 'Give me academic advice' for personalized study recommendations tailored just for you!</i>";
            
        } else {
            echo "📭 I don't see any graded assignments yet. Once you submit work and your lecturers grade it, your progress will appear here.<br><br>";
            echo "💡 <i>Say 'Pending assignments' to see what you need to submit first!</i>";
        }
        break;
    
    case 'academic_advice':
        $student_reg = $_SESSION['reg_number'] ?? null;
        
        if (!$student_reg) {
            echo "🔐 I'd love to give you personalized advice, but I need you to log in first so I can see your performance!<br><br>";
            echo "💡 <i>Once logged in, I'll analyze your grades and give you study tips!</i>";
            break;
        }
        
        $progress = getStudentAcademicProgress($conn, $student_reg);
        $advice = generateAcademicAdvice($conn, $student_reg, $progress);
        
        echo "<b>🎓 Personalized Academic Advice — Just for You!</b><br><br>";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br>";
        
        foreach ($advice as $line) {
            echo $line;
        }
        
        echo "<br>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br>";
        echo "💡 <i>Remember: Every expert was once a beginner. Keep going, and you'll get there! 🌱</i><br>";
        echo "💡 <i>Say 'My academic progress' to see your detailed performance report!</i>";
        break;
    
    case 'what_to_register':
        $student_reg = $_SESSION['reg_number'] ?? null;
        $student_department = getStudentDepartment();
        
        if (!$student_reg) {
            echo "🔐 Please log in first to see the units you need to register for this semester.<br><br>";
            echo "💡 <i>Once logged in, I can tell you exactly which units you should register for based on your department!</i>";
            break;
        }
        
        if (!$student_department) {
            echo "⚠️ Your department information is missing. Please contact the administrator.<br><br>";
            echo "💡 <i>Your department is required to show the correct units for your program.</i>";
            break;
        }
        
        $student_year = getStudentYearLevel($conn, $student_reg);
        $current_semester_num = getCurrentSemester();
        $semester_name = getSemesterName($current_semester_num);
        
        $units = getUnitsByStudentDepartment($conn, $student_department, $student_year, $current_semester_num);
        
        echo "<b>🎓 Your {$student_year} - {$semester_name} Units</b><br>";
        echo "<b>📚 Department: {$student_department}</b><br><br>";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br>";
        echo "📌 <b>You are required to register for these units this semester.</b><br>";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br><br>";
        
        if (!empty($units)) {
            $counter = 1;
            foreach ($units as $unit) {
                echo "<b>{$counter}. {$unit['unit_code']} - {$unit['unit_name']}</b><br>";
                echo "   📌 <i>Offered: {$unit['offering_time']}</i><br><br>";
                $counter++;
            }
        } else {
            echo "⚠️ No units found for your department this semester.<br>";
            echo "💡 Please contact the academic office for assistance.";
        }
        break;
    
    case 'registration_status':
        $student_reg = $_SESSION['reg_number'] ?? null;
        $student_department = getStudentDepartment();
        
        if (!$student_reg) { 
            echo "🔐 Please log in to check your registration status."; 
            break; 
        }
        
        if (!$student_department) {
            echo "⚠️ Your department information is missing. Please contact the administrator.";
            break;
        }
        
        $student_year = getStudentYearLevel($conn, $student_reg);
        $current_semester_num = getCurrentSemester();
        $semester_name = getSemesterName($current_semester_num);
        $reg_status = getStudentRegisteredUnits($conn, $student_reg, $student_year, $current_semester_num);
        
        if (isset($reg_status['error'])) { 
            echo "<b>⚠️ {$reg_status['error']}</b><br><br>";
            echo "💡 Please contact the academic office for assistance.";
            break; 
        }
        
        echo "<b>🎓 Registration Status - {$student_year}, {$semester_name}</b><br>";
        echo "<b>📚 Department: {$student_department}</b><br><br>";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br><br>";
        
        if ($reg_status['total_registered'] == $reg_status['total_required']) {
            echo "✅ <b>Excellent!</b> You have successfully registered for ALL {$reg_status['total_required']} required units!<br><br>";
            echo "<b>📚 Your registered units:</b><br>";
            foreach ($reg_status['registered_with_names'] as $unit) {
                echo "  • <b>{$unit['code']}</b> - {$unit['name']}<br>";
            }
        } else {
            echo "⚠️ <b>Incomplete Registration!</b> You have registered for {$reg_status['total_registered']} out of {$reg_status['total_required']} required units.<br><br>";
            
            if (!empty($reg_status['registered_with_names'])) {
                echo "<b>✅ Already registered:</b><br>";
                foreach ($reg_status['registered_with_names'] as $unit) {
                    echo "  • <b>{$unit['code']}</b> - {$unit['name']}<br>";
                }
                echo "<br>";
            }
            
            if (!empty($reg_status['not_registered_with_names'])) {
                echo "<b>⚠️ Missing units (YOU MUST REGISTER THESE):</b><br>";
                foreach ($reg_status['not_registered_with_names'] as $unit) {
                    echo "  • <b style='color:#ff6600;'>{$unit['code']}</b> - {$unit['name']}<br>";
                }
                echo "<br><b>🔴 Action Required:</b> Please register for the missing units immediately!<br><br>";
            }
        }
        
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br>";
        echo "<b>📋 Complete Required Units List:</b><br>";
        foreach ($reg_status['required_with_names'] as $unit) {
            $checkmark = in_array($unit['code'], $reg_status['registered']) ? "✅" : "❌";
            echo "  {$checkmark} <b>{$unit['code']}</b> - {$unit['name']}<br>";
        }
        break;
    
    case 'timetable':
        $student_reg = $_SESSION['reg_number'] ?? null;
        $student_department = getStudentDepartment();
        
        if (!$student_reg) {
            echo "🔐 Please log in to view your personal timetable.<br><br>";
            echo "💡 <i>Once logged in, I can show you your class schedule!</i>";
            break;
        }
        
        if (!$student_department) {
            echo "⚠️ Your department information is missing. Please contact the administrator.<br><br>";
            echo "💡 <i>Your department is required to show your class schedule.</i>";
            break;
        }
        
        $student_year = getStudentYearLevel($conn, $student_reg);
        $current_semester_num = getCurrentSemester();
        $semester_name = getSemesterName($current_semester_num);
        
        $timetable = getStudentTimetableByDepartment($conn, $student_department, $student_year, $current_semester_num);
        
        if (!empty($timetable)) {
            echo "<b>📅 Your {$student_year} - {$semester_name} Timetable</b><br>";
            echo "<b>📚 Department: {$student_department}</b><br><br>";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br><br>";
            
            $counter = 1;
            foreach ($timetable as $unit) {
                echo "<b>{$counter}. {$unit['unit_code']} - {$unit['course_title']}</b><br>";
                echo "   📆 <b>Day:</b> " . ($unit['day_of_week'] ?? 'TBA') . "<br>";
                echo "   ⏰ <b>Time:</b> " . ($unit['time_from'] ?? 'TBA') . " - " . ($unit['time_to'] ?? 'TBA') . "<br>";
                echo "   📍 <b>Venue:</b> " . ($unit['venue'] ?? 'TBA') . "<br>";
                echo "   👨‍🏫 <b>Lecturer:</b> " . ($unit['lecturer'] ?? 'TBA') . "<br><br>";
                $counter++;
            }
            
            $download_token = base64_encode($student_reg . '_' . time());
            $download_url = "download_timetable.php?token=" . urlencode($download_token) . "&student=" . urlencode($student_reg);
            
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br>";
            echo "📥 <a href='{$download_url}' target='_blank' style='background-color: #4CAF50; color: white; padding: 8px 16px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 10px;'>📄 Download Timetable (PDF/HTML)</a><br><br>";
            echo "💡 <i>You can also say 'Download my timetable' to get the link again.</i><br>";
        } else {
            echo "📭 No timetable found for {$student_year}, {$semester_name}.<br><br>";
            echo "💡 <i>Say 'What to register' to see the units you need for this semester!</i>";
        }
        break;
    
    case 'download_timetable':
        $student_reg = $_SESSION['reg_number'] ?? null;
        $student_department = getStudentDepartment();
        
        if (!$student_reg) {
            echo "🔐 Please log in to download your timetable.<br><br>";
            echo "💡 <i>Once logged in, I can provide your downloadable timetable!</i>";
            break;
        }
        
        if (!$student_department) {
            echo "⚠️ Your department information is missing. Please contact the administrator.";
            break;
        }
        
        $student_year = getStudentYearLevel($conn, $student_reg);
        $current_semester_num = getCurrentSemester();
        $semester_name = getSemesterName($current_semester_num);
        
        $timetable = getStudentTimetableByDepartment($conn, $student_department, $student_year, $current_semester_num);
        
        if (!empty($timetable)) {
            $download_token = base64_encode($student_reg . '_' . time());
            $download_url = "download_timetable.php?token=" . urlencode($download_token) . "&student=" . urlencode($student_reg);
            
            echo "📥 <b>Your Timetable Download Link</b><br><br>";
            echo "Click the link below to download your {$student_year} - {$semester_name} timetable:<br><br>";
            echo "🔗 <a href='{$download_url}' target='_blank' style='background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>📄 Download Timetable (HTML/PDF)</a><br><br>";
            echo "💡 <i>The timetable will open in a new tab. You can save it as PDF using your browser's print function (Ctrl+P or Cmd+P).</i><br><br>";
            echo "📌 <b>Tip:</b> You can also say 'Show my timetable' to view it directly in the chat.";
        } else {
            echo "📭 No timetable found for {$student_year}, {$semester_name}.<br><br>";
            echo "💡 <i>Please contact the academic office for your class schedule.</i>";
        }
        break;
    
    case 'my_courses':
        $student_reg = $_SESSION['reg_number'] ?? null;
        $student_department = getStudentDepartment();
        
        if (!$student_reg) {
            echo "🔐 Please log in to view your courses.";
            break;
        }
        
        if (!$student_department) {
            echo "⚠️ Your department information is missing. Please contact the administrator.";
            break;
        }
        
        $student_year = getStudentYearLevel($conn, $student_reg);
        $current_semester_num = getCurrentSemester();
        $semester_name = getSemesterName($current_semester_num);
        $reg_status = getStudentRegisteredUnits($conn, $student_reg, $student_year, $current_semester_num);
        
        echo "<b>🎓 Your Academic Standing - {$student_year}, {$semester_name}</b><br>";
        echo "<b>📚 Department: {$student_department}</b><br><br>";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br>";
        echo "<b>📋 Required Units for This Semester:</b><br>";
        
        foreach ($reg_status['required_with_names'] as $unit) {
            $checkmark = in_array($unit['code'], $reg_status['registered']) ? "✅" : "❌";
            echo "  {$checkmark} <b>{$unit['code']}</b> - {$unit['name']}<br>";
        }
        
        echo "<br><b>Progress:</b> " . round($reg_status['completion_percentage']) . "% complete<br>";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br><br>";
        
        if (!empty($reg_status['not_registered_with_names'])) {
            echo "<b>⚠️ You still need to register for these units:</b><br>";
            foreach ($reg_status['not_registered_with_names'] as $unit) {
                echo "  • <b style='color:#ff6600;'>{$unit['code']}</b> - {$unit['name']}<br>";
            }
            echo "<br>💡 <i>Say 'What to register' to see the full timetable with days and times!</i><br><br>";
        } else {
            echo "🎉 <b>Congratulations!</b> You've registered for all required units this semester!<br><br>";
        }
        break;
    
    case 'student_info':
        $student_reg = $_SESSION['reg_number'] ?? null;
        if ($student_reg) {
            $query = "SELECT full_name, reg_number, email, department FROM users WHERE reg_number = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $student_reg);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $student_year = getStudentYearLevel($conn, $student_reg);
                echo "<b>👤 Your Profile</b><br>";
                echo "• Name: {$row['full_name']}<br>";
                echo "• Registration Number: {$row['reg_number']}<br>";
                echo "• Email: {$row['email']}<br>";
                echo "• Department: <b>{$row['department']}</b><br>";
                echo "• Current Year Level: <b>{$student_year}</b><br>";
                echo "<br>💡 <i>Say 'Show my timetable' to see your class schedule!</i>";
            } else {
                echo "Hmm, I couldn't find your information. 🤔";
            }
        } else {
            echo "🔐 Please log in to view your profile information.";
        }
        break;
    
    case 'reg_help':
        $student_reg = $_SESSION['reg_number'] ?? null;
        $student_department = getStudentDepartment();
        
        if ($student_reg) {
            $student_year = getStudentYearLevel($conn, $student_reg);
            $current_semester_num = getCurrentSemester();
            $semester_name = getSemesterName($current_semester_num);
            $reg_status = getStudentRegisteredUnits($conn, $student_reg, $student_year, $current_semester_num);
            
            echo "<b>🎓 Course Registration Guide - {$student_year}, {$semester_name}</b><br>";
            if ($student_department) {
                echo "<b>📚 Department: {$student_department}</b><br><br>";
            } else {
                echo "<br>";
            }
            echo "<b>📋 You must register for these units this semester:</b><br>";
            foreach ($reg_status['required_with_names'] as $unit) {
                $status = in_array($unit['code'], $reg_status['registered']) ? "✅ Registered" : "⚪ Not Registered";
                echo "  • <b>{$unit['code']}</b> - {$unit['name']} ({$status})<br>";
            }
            echo "<br>";
            
            if (!empty($reg_status['not_registered_with_names'])) {
                echo "<b>⚠️ Missing Units to Register:</b><br>";
                foreach ($reg_status['not_registered_with_names'] as $unit) {
                    echo "  • <b>{$unit['code']}</b> - {$unit['name']}<br>";
                }
                echo "<br><b>📝 Steps to Register:</b><br>";
                echo "1️⃣ Go to the registration portal<br>";
                echo "2️⃣ Search for the missing unit codes above<br>";
                echo "3️⃣ Add them to your registration cart<br>";
                echo "4️⃣ Submit your registration<br><br>";
            } else {
                echo "✅ <b>Great news!</b> You've already registered for all required units!<br><br>";
            }
            echo "💡 <i>Say 'Show my timetable' to see your class schedule!</i><br>";
        } else {
            echo "<b>🎓 Course Registration Guide</b><br><br>";
            echo "To register for units, please follow these steps:<br><br>";
            echo "1️⃣ <b>Log in to the portal</b> using your registration number and password<br>";
            echo "2️⃣ <b>Say 'What to register'</b> to see the units you need<br>";
            echo "3️⃣ <b>Register for those units</b> in the registration portal<br>";
            echo "4️⃣ <b>Confirm your registration</b> and check your status<br><br>";
            echo "🔐 <i>Please log in first so I can show you your personalized unit list!</i>";
        }
        break;
    
    case 'view_all':
        $target_year = $GLOBALS['target_year'] ?? null;
        $student_department = getStudentDepartment();
        
        if (!$target_year) {
            $all_units = mysqli_query($conn, "SELECT year_level, COUNT(*) as count FROM timetable GROUP BY year_level");
            $summary = [];
            while ($row = mysqli_fetch_assoc($all_units)) {
                $summary[] = [$row['year_level'] . ": " . $row['count'] . " units"];
            }
            echo formatResponse($summary, "📚 Units by Year Level:");
            echo "<br><br>💡 <i>Tip: Try asking 'Show me first year units' or 'What are second year courses?'</i>";
            break;
        }
        
        if ($student_department) {
            $sem1_units = getUnitsByStudentDepartment($conn, $student_department, $target_year, 1);
            $sem2_units = getUnitsByStudentDepartment($conn, $student_department, $target_year, 2);
            
            if (!empty($sem1_units) || !empty($sem2_units)) {
                echo "<b>📖 {$target_year} Curriculum - {$student_department} Department</b><br><br>";
                
                if (!empty($sem1_units)) {
                    echo "<b>📌 1st Semester Required Units:</b><br>";
                    foreach ($sem1_units as $unit) {
                        echo "  • <b>{$unit['unit_code']}</b> - {$unit['unit_name']}<br>";
                    }
                    echo "<br>";
                }
                
                if (!empty($sem2_units)) {
                    echo "<b>📌 2nd Semester Required Units:</b><br>";
                    foreach ($sem2_units as $unit) {
                        echo "  • <b>{$unit['unit_code']}</b> - {$unit['unit_name']}<br>";
                    }
                    echo "<br>";
                }
                
                echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br>";
                echo "💡 <i>These are the units you need to complete for your {$target_year} in the {$student_department} department.</i><br>";
            } else {
                echo "<b>📖 {$target_year} Curriculum - {$student_department} Department</b><br><br>";
                echo "⚠️ No units found for {$target_year} in the {$student_department} department.<br><br>";
                echo "💡 <i>Please contact the academic office for assistance.</i>";
            }
        } else {
            $stmt = $conn->prepare("SELECT unit_code, course_title, semester, day_of_week, time_from, time_to, venue 
                                    FROM timetable 
                                    WHERE year_level = ? 
                                    ORDER BY semester, day_of_week, time_from");
            $stmt->bind_param("s", $target_year);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                echo "<b>📖 {$target_year} Curriculum:</b><br><br>";
                $units_by_semester = [];
                while ($row = $result->fetch_assoc()) {
                    $semester = ($row['semester'] == 1) ? '1st Semester' : '2nd Semester';
                    if (!isset($units_by_semester[$semester])) { $units_by_semester[$semester] = []; }
                    $units_by_semester[$semester][] = $row['unit_code'] . " - " . $row['course_title'];
                }
                foreach ($units_by_semester as $semester => $units) {
                    echo "<b>📌 {$semester}:</b><br>";
                    foreach ($units as $unit) { echo "  • {$unit}<br>"; }
                    echo "<br>";
                }
                echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br>";
                echo "💡 <i>Log in to see units specific to your department!</i><br>";
            } else {
                echo "Hmm, I couldn't find any units for {$target_year}. 🤔";
            }
        }
        break;
    
    case 'unit_details':
        $unit_code = $GLOBALS['target_unit'] ?? '';
        
        if (empty($unit_code)) {
            if (preg_match('/([A-Z]{3,4}[0-9]{4})/i', $user_input, $matches)) {
                $unit_code = strtoupper($matches[1]);
            } else {
                echo "❓ Please specify which unit you want to know about. Example: 'Tell me about BSN1106'";
                break;
            }
        }
        
        $unit_query = "SELECT * FROM academic_workload WHERE unit_code = ?";
        $unit_stmt = $conn->prepare($unit_query);
        $unit_stmt->bind_param("s", $unit_code);
        $unit_stmt->execute();
        $unit_result = $unit_stmt->get_result();
        
        if ($unit_result->num_rows > 0) {
            $unit = $unit_result->fetch_assoc();
            
            echo "<b>📖 Unit Details: {$unit['unit_code']} - {$unit['unit_name']}</b><br><br>";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br>";
            echo "📚 <b>Department:</b> {$unit['department']}<br>";
            echo "🎓 <b>Year Level:</b> {$unit['year_level']}<br>";
            echo "📅 <b>Semester:</b> {$unit['semester_level']}<br>";
            echo "🕒 <b>Offering Time:</b> {$unit['offering_time']}<br>";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━<br><br>";
            
            $current_semester_num = getCurrentSemester();
            $current_year = date('Y');
            $timetable_query = "SELECT day_of_week, time_from, time_to, venue, lecturer 
                               FROM timetable 
                               WHERE unit_code = ? AND semester = ? AND academic_year = ? 
                               LIMIT 1";
            $timetable_stmt = $conn->prepare($timetable_query);
            $timetable_stmt->bind_param("sss", $unit_code, $current_semester_num, $current_year);
            $timetable_stmt->execute();
            $timetable_result = $timetable_stmt->get_result();
            
            if ($timetable_result->num_rows > 0) {
                $schedule = $timetable_result->fetch_assoc();
                echo "<b>📅 Class Schedule:</b><br>";
                echo "   📆 <b>Day:</b> " . ($schedule['day_of_week'] ?? 'TBA') . "<br>";
                echo "   ⏰ <b>Time:</b> " . ($schedule['time_from'] ?? 'TBA') . " - " . ($schedule['time_to'] ?? 'TBA') . "<br>";
                echo "   📍 <b>Venue:</b> " . ($schedule['venue'] ?? 'TBA') . "<br>";
                if (!empty($schedule['lecturer'])) {
                    echo "   👨‍🏫 <b>Lecturer:</b> {$schedule['lecturer']}<br>";
                }
                echo "<br>";
            } else {
                echo "<b>📅 Class Schedule:</b> Not yet scheduled<br><br>";
            }
            
            $student_reg = $_SESSION['reg_number'] ?? null;
            if ($student_reg) {
                $check_reg_query = "SELECT status FROM registered_courses WHERE student_reg_no = ? AND unit_code = ?";
                $check_reg_stmt = $conn->prepare($check_reg_query);
                $check_reg_stmt->bind_param("ss", $student_reg, $unit_code);
                $check_reg_stmt->execute();
                $check_reg_result = $check_reg_stmt->get_result();
                
                if ($check_reg_result->num_rows > 0) {
                    $reg_status = $check_reg_result->fetch_assoc();
                    $status_icon = ($reg_status['status'] == 'Confirmed') ? '✅' : '⏳';
                    echo "<b>📌 Your Registration Status:</b> {$status_icon} {$reg_status['status']}<br><br>";
                } else {
                    $student_year = getStudentYearLevel($conn, $student_reg);
                    $student_department = getStudentDepartment();
                    $current_semester_num = getCurrentSemester();
                    $required_units = getUnitsByStudentDepartment($conn, $student_department, $student_year, $current_semester_num);
                    $required_codes = array_column($required_units, 'unit_code');
                    
                    if (in_array($unit_code, $required_codes)) {
                        echo "⚠️ <b>You are not registered for this unit yet.</b> This unit is REQUIRED for your program.<br><br>";
                    } else {
                        echo "ℹ️ <b>You are not registered for this unit.</b><br><br>";
                    }
                }
                $check_reg_stmt->close();
            }
            
            echo "<b>💡 What would you like to do?</b><br>";
            echo "• 'Add {$unit_code}' - Register for this unit<br>";
            echo "• 'When is {$unit_code}?' - Check schedule again<br>";
            echo "• 'What to register' - See all required units<br>";
            
        } else {
            echo "❌ I couldn't find unit '{$unit_code}' in our system.<br><br>";
            
            $fuzzy_matches = fuzzySearchUnits($unit_code, $conn, 3);
            if (!empty($fuzzy_matches)) {
                echo "<b>💡 Did you mean one of these?</b><br><br>";
                foreach ($fuzzy_matches as $match) {
                    echo "• <b>{$match['unit_code']}</b> - {$match['unit_name']}<br>";
                }
                echo "<br>Try asking: 'Tell me about " . $fuzzy_matches[0]['unit_code'] . "'";
            } else {
                echo "💡 <i>Tip: Make sure you're using the correct unit code format like 'BSN1106' or 'BAF1101'.</i><br>";
                echo "• Ask me to 'Show all first year units' to see available courses<br>";
                echo "• Say 'What to register' to see your required units";
            }
        }
        break;
    
    case 'search_unit':
        preg_match('/[A-Z]{3,4}[0-9]{4}/i', $user_input, $matches);
        $search_term = isset($matches[0]) ? $matches[0] : $user_input;
        
        $search_pattern = "%$search_term%";
        $stmt = $conn->prepare("SELECT unit_code, unit_name, year_level, semester_level, offering_time 
                                FROM academic_workload 
                                WHERE unit_code LIKE ? OR unit_name LIKE ? 
                                LIMIT 5");
        $stmt->bind_param("ss", $search_pattern, $search_pattern);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo "<b>🔍 Found matching units:</b><br><br>";
            while ($row = $result->fetch_assoc()) {
                echo "• <b>{$row['unit_code']}</b> - {$row['unit_name']}<br>";
                echo "  📚 {$row['year_level']}, {$row['semester_level']}<br>";
                echo "  🕒 Offered: {$row['offering_time']}<br><br>";
            }
            echo "💡 <i>For more details about a specific unit, say 'Tell me about " . $row['unit_code'] . "'</i><br>";
        } else {
            $fuzzy_matches = fuzzySearchUnits($search_term, $conn);
            if (!empty($fuzzy_matches)) {
                echo "<b>🔍 Did you mean one of these?</b><br><br>";
                foreach ($fuzzy_matches as $match) {
                    echo "• <b>{$match['unit_code']}</b> - {$match['unit_name']}<br>";
                }
                echo "<br>💡 <i>Say 'Tell me about " . $fuzzy_matches[0]['unit_code'] . "' for details!</i><br>";
            } else {
                echo "🔍 Hmm, I couldn't find '$search_term' in our system.<br><br>";
                echo "💡 <i>Say 'What to register' to see all required units for your department!</i><br>";
            }
        }
        break;
    
    case 'unit_day':
        $unit_code = $GLOBALS['target_unit'] ?? '';
        
        if (empty($unit_code)) {
            if (preg_match('/([A-Z]{3,4}[0-9]{4})/i', $user_input, $matches)) {
                $unit_code = strtoupper($matches[1]);
            } else {
                echo "❓ Please specify which unit you want to know about. Example: 'When is BSN1106?'";
                break;
            }
        }
        
        $current_semester_num = getCurrentSemester();
        $current_year = date('Y');
        
        $query = "SELECT day_of_week, time_from, time_to, venue, lecturer, course_title 
                 FROM timetable 
                 WHERE unit_code = ? AND semester = ? AND academic_year = ? 
                 LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sss", $unit_code, $current_semester_num, $current_year);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            echo "<b>📚 {$unit_code} - {$row['course_title']}</b><br><br>";
            if (!empty($row['day_of_week']) && $row['day_of_week'] != '0000-00-00') {
                echo "📆 <b>Day:</b> {$row['day_of_week']}<br>";
                echo "⏰ <b>Time:</b> {$row['time_from']} - {$row['time_to']}<br>";
                echo "📍 <b>Venue:</b> {$row['venue']}<br>";
                echo "👨‍🏫 <b>Lecturer:</b> {$row['lecturer']}<br><br>";
                echo "💡 <i>Would you like to know about any other unit's schedule?</i>";
            } else {
                echo "❌ The schedule for {$unit_code} hasn't been released yet.<br><br>";
            }
        } else {
            echo "❌ I couldn't find the schedule for {$unit_code}.<br><br>";
            echo "💡 Try asking: 'Tell me about {$unit_code}' for more details, or 'What to register' to see all required units.";
        }
        break;
    
    case 'unit_registration_count':
    case 'course_advice':
    case 'exam_info':
        echo getCreativeUnknownResponse();
        break;
    
    case 'ai_check':
        echo "🤖 I'm your friendly AI academic assistant! Here's what I can do:<br><br>
              ✅ <b>Show my timetable</b> - Displays your class schedule with days, times, and venues<br>
              ✅ <b>Download my timetable</b> - Get a downloadable link for your timetable<br>
              ✅ <b>What to Register</b> - Shows the exact units you need for your current semester<br>
              ✅ <b>Registration Status</b> - Check which required units you've registered for<br>
              ✅ <b>My Courses</b> - View your academic standing and progress<br>
              ✅ <b>Student Info</b> - View your profile information including department<br>
              ✅ <b>Course Advisor</b> - Get personalized course recommendations<br>
              ✅ <b>Registration Help</b> - Guide you through the registration process<br>
              ✅ <b>View Units by Year</b> - See all units for any year level (filtered by your department!)<br>
              ✅ <b>Unit Details</b> - Get detailed info about any unit (e.g., 'Tell me about BSN1106')<br>
              ✅ <b>Unit Schedule</b> - Find out when a unit is taught (e.g., 'When is BSN1106?')<br><br>
              🆕 <b>NEW FEATURES:</b><br>
              ✅ <b>List Department Lecturers</b> - See all lecturers in your department (e.g., 'List lecturers in my department')<br>
              ✅ <b>Lecturer Information</b> - Get details about any lecturer (e.g., 'Who is Dr. Peter Kamau?')<br>
              ✅ <b>Lecturer Units</b> - See which units a lecturer teaches (e.g., 'What units does Arfican teach?')<br>
              ✅ <b>Pending Assignments</b> - Check all upcoming assignments and deadlines<br>
              ✅ <b>Assignment Deadlines</b> - Get specific deadline for a unit<br>
              ✅ <b>Academic Progress</b> - View your performance and grades<br>
              ✅ <b>Academic Advice</b> - Get personalized study recommendations<br>
              ✅ <b>When am I finishing?</b> - Check your graduation timeline and remaining semesters<br><br>
              🎓 <b>Try asking:</b><br>
              • 'List my lecturers' - See everyone teaching in your department!<br>
              • 'Show my timetable' - See your complete class schedule!<br>
              • 'What to register' - See your required units for this semester!<br>
              • 'When am I finishing?' - Check when you'll graduate!<br>
              • 'Do I have any pending assignments?' - Check upcoming deadlines!<br>
              • 'My academic progress' - View your performance across all units!<br>
              • 'Give me academic advice' - Get personalized study tips!<br><br>
              What would you like to know today? 😊";
        break;
    
    case 'fallback':
    default:
        $stmt_msg = $conn->prepare("INSERT INTO chat_messages (conversation_id, sender_type, message) VALUES (?, 'student', ?)");
        $stmt_msg->bind_param("ss", $sess_id, $user_input);
        $stmt_msg->execute();
        
        $bot_msg = getCreativeUnknownResponse();
        echo $bot_msg;
        
        $stmt_bot = $conn->prepare("INSERT INTO chat_messages (conversation_id, sender_type, message) VALUES (?, 'bot', ?)");
        $stmt_bot->bind_param("ss", $sess_id, $bot_msg);
        $stmt_bot->execute();
        break;
}
?>