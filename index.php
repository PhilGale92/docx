<?php
    /*
     * This file is for demonstration purposes only
     * This has no real security checks, or rate limiting
     */

    /*
     * Include the library
     */
    require_once('Docx/DocxFileManipulation.class.php');
    require_once('Docx/Docx.class.php');
    require_once('Docx/LinkAttachment.class.php');
    require_once('Docx/FileAttachment.class.php');
    require_once('Docx/Nodes/Node.class.php');
    require_once('Docx/Nodes/Para.class.php');
    require_once('Docx/Nodes/Run.class.php');
    require_once('Docx/Style.class.php');


    /*
     * Include html display for the demo
     */
    $demoOnUploadedFileName = '';
    $absRoot = __DIR__ . DIRECTORY_SEPARATOR;
    require_once('html.tpl.php');

    /*
     * If we want to demo on a preUpload file, assign the path to here
     */
    $demoOnUploadedFileName = 'WordDocxFix.docx';

	/*
	 * If the target upload directory does not exist, create it 
	 */
	$uploadDir = $absRoot . 'tmp' . DIRECTORY_SEPARATOR ;
	if (!file_exists($uploadDir)) {
		mkdir($uploadDir, 0775, true);
	}
	
    /*
     * Display & process the form
     * Dispatch form results to Docx system
     */
    ob_start();
	
	$showForm = true;
	if ($demoOnUploadedFileName != null )
	    $fileUri = $uploadDir . $demoOnUploadedFileName;
	else
	    $fileUri = null ;

	if (isset($_POST['word_submit'])){
		if (isset($_FILES['word_file']['name'])){
			$fileName = $_FILES['word_file']['name'];
			if (substr($fileName, -5) == '.docx'){
                
				$fileUri = $uploadDir . $fileName;
				move_uploaded_file($_FILES['word_file']['tmp_name'], $fileUri);

			} else echo 'Docx file extension only';
		}
	}
	if ($fileUri != null ) {
        # Parse
        $parser = new Docx\Docx($fileUri );
        $parser
            ->render('html')
        ;
        echo $parser->html;
        $showForm = false;
    }
	
	if ($showForm){
		echo '<h1>Docx Parser</h1>';
		echo '<form enctype="multipart/form-data" action="" method="post">';
			echo '<label for="word_file">Upload .docx file here</label><br/>';
			echo '<input type="file" name="word_file" /><br/><br/>';
			echo '<input type="submit" name="word_submit" value="Upload" />';
		echo '</form>';
	}
	
	$contents = ob_get_clean();
	
	echo $header;
		echo $contents;
	echo $footer;