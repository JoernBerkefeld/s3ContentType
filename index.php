<?php

##########
###
#	-
#	- ADD YOUR OWN USER ACCOUNT CHECKING HERE TO VALIDATE THE UPLOAD! 
#	- 
###
##########
$S3_BUCKET = 'my-s3-bucket';
$S3_credentialsFile = 'demo.inc.php'; // in config-folder (folder can be changed in s3.inc.php)




$filename = isset($_GET['file']) && $_GET['file'] ? $_GET['file'] : '';
$mime = isset($_GET['mime']) && $_GET['mime'] ? $_GET['mime'] : '';
$folder = (isset($_GET['folder']) && $_GET['folder']? $_GET['folder'] : '');

$folder = $folder && substr($folder, -1) !== '/'? $folder.'/' : $folder; // add trailing slash in case...

?>
<html>
<head>
<style>
body {
	font-family: Arial;
}
label {
	display: block;
	margin-bottom:20px;
}
label>input {
	margin-left:5px;
}
.req {
	border-color:red;
}
</style>
</head>
<body>

<?php

if($api && $filename && $mime && isset($_GET['go'])) {
	require_once 's3.inc.php';


	echo '<pre>';

	# create client
	$s3Upload = new S3Upload($S3_BUCKET, $S3_credentialsFile, $folder);

	$changeContentType = $s3Upload->changeContentType($filename, $mime);

	echo '</pre>';
}

?>
<div><i>Result-set will be cut off after 1000 entries! might require using the</i> folders <i>input to update further files</i></div>
<br>
<form>
<label for="api">api:<br>
<input type="text" class="req" name="api" id="api" value="<?=$api?>"></label>

<label for="folder">folder (my/path/; optional; warning: api-subfolder has to be set manually here if api=dev is used!):<br>
<input type="text" class="" name="folder" id="folder" value="<?=$folder?>"></label>

<label for="file">file (*.mp4, video.mp4):<br>
<input type="text" class="req" name="file" id="file" value="<?=$filename?>"></label>

<label for="mime">MIME type (video/mp4, video/webm):<br>
<input type="text" class="req" name="mime" id="mime" value="<?=$mime?>"></label>

<input type="submit" name="go">
</form>
</body>
</html>
