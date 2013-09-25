<?php
	require_once('html.tpl.php');
	require_once('WordExtractor.class.php');
	require_once('WordRender.class.php');
	
	$contents .= '<form action="" method="post" enctype="multipart/form-data"> ';
	$contents .= '<fieldset>';
	$contents .= '<input type="file" name="upload_file" id="upload_file" />';
	$contents .= '<br/><input type="submit" value="Submit" />';
	$contents .= '</fieldset>';
	$contents .= '</form>';
	
	ob_start();
	if (isset($_FILES['upload_file'])){
		$uploadedFileName = $_FILES['upload_file']['name'];
		if (substr($uploadedFileName, -4) != 'docx') die('invalid file ext. (needs docx)');
		
		$destinationUri = __DIR__ . '/tmp/' . $uploadedFileName;
		move_uploaded_file($_FILES['upload_file']['tmp_name'], $destinationUri);
		
		$extract = new WordRender($destinationUri);
		$extract->extract();
		$extract->toHtml();
		echo $extract;
	}
	
	$contents .= ob_get_clean();
	
	echo $header;
	echo $contents;
	echo $footer;
