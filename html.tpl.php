<?php
	ob_start();
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>Word cleaner</title>
	<style type="text/css">
		.tab_placeholder { display:inline-block; padding-left:20px; } 
	</style>
</head>
<body>
	<div id="page_wrapper">
		<div class="inner">
	<?php
	$header = ob_get_clean();
	
	ob_start(); ?>
</div></div>
</body></html>
	<?php
	$footer = ob_get_clean();
	$contents = '';