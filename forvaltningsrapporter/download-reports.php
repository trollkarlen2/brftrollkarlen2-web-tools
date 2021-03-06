<?php
const COOKIE_HEADER_START = "Set-Cookie: ";

// Include Composer autoloader if not already done.
include '../vendor/autoload.php';

require_once 'renderer/HtmlRenderer.php';
require_once 'ReportReader.php';
require_once 'config.php';
require_once '../lib/PdfParser.php';

$cookie = null;

function getAuthCookie($stofastUser, $stofastPassword)
{
    global $cookie;
    if (!isset($cookie)) {
        $modDate = getModDate();

        $cookieHeader = getAuthCookieHeader($modDate, $stofastUser, $stofastPassword);

        $cookie = "Userid=" . $stofastUser . ";" . strtok($cookieHeader, ";");
    }
    return $cookie;
}

function getAuthCookieHeader($modDate, $stofastUser, $stofastPassword)
{
    $params = array(
        "%%ModDate" => $modDate,
        "RedirectTo" => "/ts/gosud.nsf/redirect_login?openagent",
        "Username" => $stofastUser,
        "Password" => $stofastPassword
    );

    $ch = curl_init('https://entre.stofast.se/names.nsf?Login');
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    $c = curl_exec($ch);
    curl_close($ch);

    $pos = strpos($c, COOKIE_HEADER_START);
    $cookieValuePos = $pos + strlen(COOKIE_HEADER_START);
    $setCookieHeader = substr($c, $cookieValuePos, strpos($c, ";", $cookieValuePos) - $cookieValuePos);

    if (empty($setCookieHeader)) {
        die("Empty cookie");
    }
    return $setCookieHeader;
}

function getModDate()
{
    $ch1 = curl_init('https://entre.stofast.se/');
    curl_setopt($ch1, CURLOPT_HEADER, true);
    curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
    $c1 = curl_exec($ch1);
    curl_close($ch1);

    $match = [];
    preg_match('/[0-9A-F]{16}/', $c1, $match);
    $ModDate = $match[0];
    return $ModDate;
}

function downloadFileFromUrl($filename, $url, $stofastUser, $stofastPassword)
{
    $fp = fopen($filename, 'wb');
    $handle = curl_init($url);
    curl_setopt($handle, CURLOPT_HTTPHEADER, array("Cookie: " . getAuthCookie($stofastUser, $stofastPassword)));
    curl_setopt($handle, CURLOPT_FILE, $fp);
    curl_setopt($handle, CURLOPT_HEADER, false);
    curl_exec($handle);
    $contentType = curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
    curl_close($handle);
    fclose($fp);
    return $contentType;
}

$timestamp = date('Ymd');
$force = "true" == $_GET["force"];

$cfg = parse_ini_file("config.ini", true);

$downloadReportsToday = in_array(substr($timestamp, -2), explode(',', $cfg['reports']['trigger_days_of_months']));

$mailTo = $cfg['mail']['to'];
$stofastUser = $cfg['entre']['username'];
$stofastPassword = $cfg['entre']['password'];

function sendMail($to, $subject, $template, $templateProperties)
{
    $body = utf8_decode(file_get_contents($template));
    $body = str_replace(array_keys($templateProperties), array_values($templateProperties), $body);
    $additionalHeaders = implode("\r\n", array("From: info@trollkarlen2.se", "Content-Type: text/plain; charset=UTF-8"));
    mail(
        $to,
        $subject,
        utf8_encode($body),
        $additionalHeaders);
}

$savedReports = [];
if ($force || $downloadReportsToday) {
    mkdir($cfg['reports']['archive_folder'], 0700, true);
    foreach ($REPORTS as $title => $reportCfg) {
        $url = $reportCfg->getUrl();
        if (empty($url)) {
            continue;
        }
        $filename = $cfg['reports']['archive_folder'] . $title . '-' . $timestamp . '.pdf';
        $isReportDownloaded = file_exists($filename);
        if ($force || !$isReportDownloaded) {
            $contentType = downloadFileFromUrl($filename, $url, $stofastUser, $stofastPassword);
            if ($contentType == 'application/pdf') {
                $savedReports[] = $filename;
                if ($reportCfg->getAfterDownloadProcessor() != null) {
                    // The after-download processor can, for example, be used to split PDFs after downloading them.
                    $afterDownloadProcessor = $reportCfg->getAfterDownloadProcessor();
                    $afterDownloadProcessor($filename);
                }
            } else {
                echo "Got $contentType instead of application/pdf";
                $props = array(
                    'SCRIPT_PATH' => $_SERVER['PHP_SELF'],
                    'FILE_PATH' => $filename,
                    'CONTENT_TYPE' => $contentType,
                    'STOFAST_USERNAME' => STOFAST_USERNAME
                );
                sendMail($mailTo,
                    "[Forvaltningsrapporter] Kunde inte ladda ner rapport",
                    "download-report-mail-contenttypeerror.utf8.txt",
                    $props);
                break;
            }
        }
    }
    if (count($savedReports) > 0) {
        sendMail($mailTo,
            "[Forvaltningsrapporter] Rapporter nedladdade",
            "download-report-mail-savedreports.utf8.txt",
            array("REPORTS" => implode("\n - ", $savedReports)));
    }
    echo "Done";
} else {
    echo "No reports will be downloaded today";
}
?>