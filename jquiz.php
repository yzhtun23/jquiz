<?php
error_reporting(0);
//FOR PROF. error reporting does not work for php 5.6
//mysqld does not work with PHP7
/* ----- System Constants ----- */
header("Content-type: text/javascript; charset=UTF-8");
define("MYSQL_USER", "root"); // MySQL username    jquizuser
define("MYSQL_PASS", ""); // MySQL password   jquizpass
define("MYSQL_DATABASE", "jquiz"); // MySQL database name
define("DEBUG_MODE", "0"); // 1 if Debug mode

/* ----- System Check ----- */
$link = mysql_connect("localhost", MYSQL_USER, MYSQL_PASS) or die("Could not connect to MySQL database");
mysql_select_db(MYSQL_DATABASE, $link) or die("Could not use the MySQL table '".MYSQL_DATABASE."'");
mysql_query("SET NAMES utf8", $link);

/* ----- Function to retrieve a column value from database ----- */
function get_a_column($sql)
{
    global $link;
    $ret = get_a_record($sql, $link);
    if ($ret) {
        return $ret[0];
    } else {
        return false;
    }
}

/* ----- Function to retrieve a record from database ----- */
function get_a_record($sql)
{
    global $link;
    if (DEBUG_MODE) {
        echo "<p class='debug'><strong>get_a_record(): </strong>".htmlspecialchars($sql)."</p>\n";
    }
    $result = mysql_query($sql, $link);
    if (!$result || mysql_num_rows($result)==0) {
        if (DEBUG_MODE && mysql_error($link)!='') {
            echo "<p class='debug'><strong>get_a_record():</strong> ".htmlspecialchars($sql).'<br />'.mysql_error($link)."</p>\n";
        }
        return false;
    }
    $ret = mysql_fetch_assoc($result);
    if (DEBUG_MODE) {
        echo "<pre class='debug'>\n";
        print_r($ret);
        echo "</pre>\n";
    }
    return $ret;
}

/* ----- Function to retrieve specified record in a table ----- */
function get_a_record_of($table, $id)
{
    global $link;
    return get_a_record("SELECT * FROM $table WHERE id=$id", $link);
}

/* ----- Function to retrieve multiple records ----- */
function get_records($sql, $key_column = "", $val_column = "")
{
    global $link;
    $ret = array();
    if (DEBUG_MODE) {
        echo "<p class='debug'><strong>get_records(): </strong>$key_column, $val_column, ".htmlspecialchars($sql)."</p>\n";
    }
    $result = mysql_query($sql, $link);
    if (!$result) {
        if (DEBUG_MODE && mysql_error($link)!='') {
            echo "<p class='debug'><strong>get_records():</strong> ".htmlspecialchars($sql).'<br />'.mysql_error($link)."</p>\n";
        }
    } elseif (mysql_num_rows($result)>0) {
        /* ----- Judge return format ----- */
        if ($key_column==="") {
            if ($val_column==="") {
                $kind = 0;
            } else {
                $kind = 1;
            }
        } else {
            if ($val_column==="") {
                $kind = 2;
            } else {
                $kind = 3;
            }
        }
        /* ----- Prepare for the returned values ----- */
        for ($i=0; $i<mysql_num_rows($result); $i++) {
            $row_all = mysql_fetch_array($result);
            mysql_data_seek($result, $i);
            $row_num = mysql_fetch_row($result);
            mysql_data_seek($result, $i);
            $row_asc = mysql_fetch_assoc($result);
            switch ($kind) {
            case 0: $ret[] = $row_asc; break;
            case 1: $ret[] = $row_all[$val_column]; break;
            case 2: $ret[$row_all[$key_column]] = $row_asc; break;
            case 3: $ret[$row_all[$key_column]] = $row_all[$val_column]; break;
            }
        }
    }
    if (DEBUG_MODE) {
        echo "<pre class='debug'>\n";
        print_r($ret);
        echo "</pre>\n";
    }
    return $ret;
}

/* ----- Function to get a single quiz object ----- */
function get_a_quiz($qid, $uid)
{
    if (!$quiz = get_a_record("SELECT quiz.*, user.name AS `author` FROM quiz, user WHERE quiz.id=$qid AND quiz.user_id=user.id")) {
        return false;
    }
    $choices = get_records("SELECT choice_num, choice_text FROM choices WHERE quiz_id=$qid ORDER BY choice_num", 0, 1);
    foreach ($choices as $idx=>$value) {
        $choices[$idx] = htmlspecialchars($value, ENT_QUOTES);
    }
    $json_object = array("qid"=>$qid, "question"=>htmlspecialchars($quiz["question"], ENT_QUOTES), "author"=>htmlspecialchars($quiz["author"], ENT_QUOTES), "choices"=>$choices);
    /* ----- If the user has already answered or the user is the author, then show current statistics ----- */
    $user_answered  = get_a_record("SELECT * FROM answer WHERE quiz_id=$qid AND user_id=$uid"); // Return Non-False if the user has already answered the quiz
    $user_is_author = get_a_record("SELECT * FROM quiz WHERE id=$qid AND user_id=$uid"); // Return Non-False if the user is the author of the quiz
    if ($user_answered || $user_is_author) {
        $num_answers = get_records("SELECT choice_num, COUNT(*) FROM answer WHERE quiz_id=$qid GROUP BY choice_num ORDER BY choice_num", 0, 1);
        $json_object["answers"] = array();
        foreach ($choices as $idx=>$choice) {
            $json_object["answers"][$idx] = isset($num_answers[$idx])? (int)$num_answers[$idx]:0;
        }
        if ($quiz["correct_answer"]>0) {
            $json_object["correct_answer"] = $quiz["correct_answer"];
        }
        if ($user_answered) {
            $json_object["your_answer"] = $user_answered["choice_num"];
        }
    }
    $json_object["closed"] = $quiz["closed"];
    if (DEBUG_MODE) {
        echo "<pre>";
        print_r($json_object);
        echo "</pre>";
    }
    return $json_object;
}

function intGET($key)
{
    return max(0, (int)@$_GET[$key]);
}

function strGET($key)
{
    global $link;
    return mysql_real_escape_string(trim(@$_GET[$key]), $link);
}

/* ----- Get parameters ----- */
// Mandatory parameters
if (empty($_GET)) {
    exit;
}
$callback = htmlspecialchars(trim(@$_GET["callback"], ENT_QUOTES)); // Name of callback function
if ($callback=='') {
    exit;
}
$cmd = strGET("cmd");            // Command name
// Parameters for login
$uname = strGET("name");        // User name
$upass = md5(strGET("pass"));    // User password (MD5)
// Common parameters
$qid = intGET("qid");            // Quiz ID
$uid = intGET("uid");            // User ID
// Parameters for showing questions
$start = intGET("start");        // Starting number of list of quizes
$rows = intGET("rows");            // Number of quizes to get
if (!isset($_GET["flags"])) {
    $flags = "YUAC";
} else {
    $flags = strGET("flags");
}    // Option flags to show questions. Combination of 1-letter initials for [Y]our/[U]nanswered/[A]nswered/[C]losed
// Parameters for posting answer
$ans = intGET("ans");            // User's answer as number
// Parameters for submitting new question
$question = strGET("question");    // Question text for the new quiz
$choices = @$_GET["choices"];    // Choices for the new quiz (must be array)
if (is_array($choices)) {
    foreach ($choices as $idx=>$value) {
        $choices[$idx] = mysql_real_escape_string(trim($value), $link);
        if ($choices[$idx]=="") {
            $choices = false;
            break;
        }
    }
}
$correct = intGET("correct");    // Correct answer (as number)

/* ----- Perform Command ----- */
switch (strtolower($cmd)) {
    case "login": // Login user
        $ret = 0; // Default return value (invalid)
        if ($uname!="" && $result = get_a_record("SELECT id FROM user WHERE name='$uname' AND password='$upass'")) {
            $ret = $result["id"];
        }
        echo "$callback({'result':'$ret'})";
        break;
    case "register": // Register a new user
        $ret = 0; // Default return value (invalid)
        if ($uname!="" && !get_a_record("SELECT * FROM user WHERE name='$uname'")) {
            mysql_query("INSERT INTO user SET name='$uname', password='$upass'", $link);
            $ret = mysql_insert_id($link); // Newly assigned ID
        }
        echo "$callback({'result':'$ret'})";
        break;
    case "get": // Get a quiz with current answers
        echo "$callback(".json_encode(get_a_quiz($qid, $uid)).")";
        break;
    case "gets": // Get multiple quizes with current answers sorted by unanswered quiz and new quizes
        $all_quizes  = get_records("SELECT * FROM quiz ORDER BY created DESC", 0, ""); // All quiz records
        $answered_quizes    = get_records("SELECT quiz_id FROM answer WHERE user_id=$uid ORDER BY quiz_id", "", 0); // Array of answered quiz IDs
        $quizes = array();
        foreach ($all_quizes as $qid=>$quiz) {
            $to_show = true;
            if ($quiz["user_id"]==$uid) {
                $to_show = strpos($flags, "Y")!==false;
            } elseif (strpos($flags, "C")===false && $quiz["closed"]==1) {
                $to_show = false;
            } elseif (strpos($flags, "U")===false && !in_array($qid, $answered_quizes)) {
                $to_show = false;
            } elseif (strpos($flags, "A")===false && in_array($qid, $answered_quizes)) {
                $to_show = false;
            }
            if ($to_show) {
                $quizes[] = $qid;
            }
        }
        if ($start>0 || $rows>0) {
            $quizes = array_slice($quizes, $start, $rows);
        }
        $obj = array();
        foreach ($quizes as $qid) {
            $obj[] = get_a_quiz($qid, $uid);
        }
        echo "$callback(".json_encode($obj).")";
        break;
    case "post": // Post an answer
        if (!get_a_record("SELECT * FROM answer WHERE quiz_id=$qid AND user_id=$uid")) {
            mysql_query("INSERT INTO answer SET quiz_id=$qid, user_id=$uid, choice_num=$ans", $link);
        }
        echo "$callback(".json_encode(get_a_quiz($qid, $uid)).")";
        break;
    case "new": // Create new quiz
        if ($uid>0 && $question!="" && is_array($choices)
        && !get_a_record("SELECT * FROM quiz WHERE question='$question'")) {
            mysql_query("INSERT INTO quiz SET user_id=$uid, question='$question', correct_answer=$correct", $link);
            $new_id = mysql_insert_id($link); // Newly assigned ID
            foreach ($choices as $idx=>$value) {
                mysql_query("INSERT INTO choices SET quiz_id=$new_id, choice_text='$value', choice_num=".($idx+1), $link);
            }
            $obj = get_a_quiz($new_id, $uid);
        } else {
            $obj = array();
        }
        echo "$callback(".json_encode($obj).")";
        break;
    case "close": // Close a quiz
        mysql_query("UPDATE quiz SET closed=1 WHERE id=$qid AND user_id=$uid", $link); // Prevent closing by non-author
        echo "$callback(".json_encode(get_a_quiz($qid, $uid)).")";
        break;
    case "open": // (Re)Open a quiz (that has been closed)
        mysql_query("UPDATE quiz SET closed=0 WHERE id=$qid AND user_id=$uid", $link); // Prevent opening by non-author
        echo "$callback(".json_encode(get_a_quiz($qid, $uid)).")";
        break;
}
