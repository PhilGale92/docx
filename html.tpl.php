<?php
	ob_start();
?><!doctype html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>Docx Parser</title>
	<style type="text/css">
		table { border-collapse:collapse;} 
		th {    text-align: left;
    text-transform: none;}
		td, th { 
			vertical-align:top;
			background-clip:padding-box;
		    border:1px solid #000000;
		    color: #414042;
		    height: 34px;
		    padding-left: 6px;
		    position: relative;
 	   }
 	   td.has_subcell  {padding-left:0;}
		table table { width:100%; }
		td td { height:72px;  border:none; border-bottom:1px solid black; min-width:110px;} 
		td table tr:last-of-type td { border-bottom:0;}
		.vmerge td {  }
		span.indent { padding-left:36px;} 
	</style>
</head>
<body><?php $header = ob_get_clean(); ob_start(); ?>
</body>
</html><?php  $footer = ob_get_clean(); ?>