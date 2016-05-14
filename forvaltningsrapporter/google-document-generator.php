<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'google-util.php';

$isAccessTokenSet = isset($_SESSION['access_token']) && $_SESSION['access_token'];
if (!$isAccessTokenSet) {
    header('Location: ' . filter_var(GOOGLE_OAUTHCALLBACK_URI, FILTER_SANITIZE_URL));
}

$client = createGoogleClient();

$client->setAccessToken($_SESSION['access_token']);
$drive_service = new Google_Service_Drive($client);

if (isset($_GET['template'])) {
    $template = $_GET['template'];
    $exported = $drive_service->files->export("1KmrpGU8s40ZQ1RWhzXmr9Oy5OZg9rtzcaJ7XZqerguU", "application/rtf", ['alt' => 'media']);

    $fileMetadata = new Google_Service_Drive_DriveFile(array(
        'name' => 'Nytt testdokument - ' . time(),
        'mimeType' => 'application/vnd.google-apps.document'));
    $content = $exported;//file_get_contents('files/report.csv');
    foreach ($_GET as $key => $value) {
        $content = str_replace($key, utf8_decode($value), $content);
    }

    $file = $drive_service->files->create($fileMetadata, array(
        'data' => $content,
        'mimeType' => 'application/rtf',
        'uploadType' => 'multipart',
        'fields' => 'id'));

    header("Content-Type: application/pdf");
    $pdfData = $drive_service->files->export($file->getId(), "application/pdf", ['alt' => 'media']);
    print $pdfData;
    return;
}
?>
<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title></title>
    <!-- Latest compiled and minified CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css">

    <!-- Optional theme -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap-theme.min.css">
    <!--Load the AJAX API-->
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
</head>
<body>
<div class="container">
    <h1>Skapa dokument</h1>
    <form action="google-document-generator.php" method="get" class="form-horizontal">
        <?php
        $files_list = $drive_service->files->listFiles(array("q" => "mimeType = 'application/vnd.google-apps.document'"))->getFiles();
        ?>
        <h3>Vilken mall vill du anv&auml;nda?</h3>
        <div class="form-group">
            <label for="template" class="col-sm-2 control-label">Mall</label>

            <div class="col-sm-10">
                <select name="template" class="form-control">
                    <?php
                    foreach ($files_list as $file) {
                        printf('<option value="%s">%s</option>', $file->getId(), $file->getName());
                    }
                    ?>
                </select>
            </div>
        </div>

        <h3>Vad ska det st&aring; i dokumentet?</h3>
        <?php foreach ($_GET as $key => $value) { ?>
            <div class="form-group">
                <label for="field-<?= $key ?>" class="col-sm-2 control-label"><?= $key ?></label>

                <div class="col-sm-10">
                    <input type="text" class="form-control" id="field-<?= $key ?>" name="<?= $key ?>"
                           value="<?= $value ?>">
                </div>
            </div>
        <?php } ?>
        <div class="form-group">
            <div class="col-sm-offset-2 col-sm-10">
                <button type="submit" class="btn btn-default">Skapa dokument</button>
            </div>
        </div>
    </form>
    <p>
        <a href="auth-signout.php">Logga ut</a>
    </p>
</div>
</body>
</html>
