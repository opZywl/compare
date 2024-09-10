<?php

include "root.php";
require_once "resources/require.php";
require_once "resources/check_auth.php";

if (permission_exists('cti_view')) {
    /**
                        access granted
     */
} else {
    echo "access denied";
    exit;
}

/**
                add multi-lingual support
 */

require_once "app_languages.php";
foreach ($text as $key => $value) {
    $text[$key] = $value[$_SESSION['domain']['language']['code']];
}

if (isset($_POST)) {
    $rtc_history_uuid = uuid();
    $call_uuid = $_POST["callID"];
    $rtc_history_direction = $_POST["dir"];
    $rtc_history_name = $_POST["rtc_history_name"];
    $rtc_history_number = $_POST["cidStr"];
    $rtc_history_extension = $_SESSION["user"]["extension"][0]["user"];
    $rtc_history_date = date("Y-m-d H:i:s");

    $rtc_history_name_idx = substr($rtc_history_number, -9, 1);

    if ($rtc_history_name_idx == 9) {
        $rtc_history_number = substr($rtc_history_number, -11);
    } else {
        $rtc_history_number = substr($rtc_history_number, -10);
    }

    $sql = "insert into v_rtc_history ";
    $sql .= "(";
    $sql .= "rtc_history_uuid, ";
    $sql .= "domain_uuid, ";
    $sql .= "call_uuid, ";
    $sql .= "rtc_history_direction, ";
    $sql .= "rtc_history_name, ";
    $sql .= "rtc_history_number, ";
    $sql .= "rtc_history_extension, ";
    $sql .= "rtc_history_date ";
    $sql .= ") ";
    $sql .= "values ";
    $sql .= "(";
    $sql .= "'$rtc_history_uuid', ";
    $sql .= "'$domain_uuid', ";
    $sql .= "'$call_uuid', ";
    $sql .= "'$rtc_history_direction', ";
    $sql .= "'$rtc_history_name', ";
    $sql .= "'$rtc_history_number', ";
    $sql .= "'$rtc_history_extension', ";
    $sql .= "'$rtc_history_date' ";
    $sql .= ")";
    //error_log($sql);
    $db->exec(check_sql($sql));
    unset($sql);
} else {
    error_log("history_calls.php not post");
}

$html  = '<div class="timeline-block">';
$html .= '      <div class="timeline-point complete">';
$html .= '              <i class="pg-telephone"></i>';
$html .= '      </div>';
$html .= '      <div class="timeline-content">';
$html .= '              <div class="card social-card share full-width">';
$html .= '                      <div class="card-header clearfix">';
$html .= '                              <h5>@CallNumber';
$html .= '                                      <span></span>';
$html .= '                                      <span class="callHistory" data-history-call="@CallNumber"><i class="fa fa-phone"></i></span>';
$html .= '                              </h5>';
$html .= '                              <h6>';
$html .= '                                      @Direction';
$html .= '                              </h6>';
$html .= '                      </div>';
$html .= '              </div>';
$html .= '              <div class="event-date">';
$html .= '                      <small class="fs-12 hint-text">' . date("d F Y") . '</small>';
$html .= '                      <small class="fs-12 hint-text">' . date("H:i:s") . '</small>';
$html .= '              </div>';
$html .= '      </div>';
$html .= '</div>';

if (strlen($rtc_history_direction) > 0) {
    if ($rtc_history_direction == "inbound") {
        $rtc_history_direction = $text['label-inbound'];
    }

    if ($rtc_history_direction == "outbound") {
        $rtc_history_direction = $text['label-outbound'];
    }
}

$html = str_replace("@Direction", $rtc_history_direction, $html);
$html = str_replace("@CallNumber", $rtc_history_number, $html);

die($html);
