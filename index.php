<?php 
	define("DIR_SEP", DIRECTORY_SEPARATOR);
	$absRoot = __DIR__ . DIR_SEP;
	require_once('html.tpl.php');
	require_once('functions.php');
	require_once('krumo/class.krumo.php');
	require_once('Docx/Docx.class.php');
	require_once('Docx/Node.class.php');
	require_once('Docx/Style.class.php');
	ob_start();
	
	$showForm = true;
	if (isset($_POST['word_submit'])){
		if (isset($_FILES['word_file']['name'])){
			$fileName = $_FILES['word_file']['name'];
			if (substr($fileName, -5) == '.docx'){
				$fileUri = $absRoot . 'tmp' . DIR_SEP . $fileName;
				move_uploaded_file($_FILES['word_file']['tmp_name'], $fileUri);
				
				# Style abstraction
				$chapterHeading = new Docx\Style('0ChapterHeading', array('htmlTag' => 'h1', 'htmlClass' => 'chapter_heading'));
				$contentsTopicHeader = new Docx\Style('C1ContentsPageTopicHeader', array('htmlTag' => 'h3', 'htmlClass' => 'topic_header'));
				$contentsPageSubHeader = new Docx\Style('C2ContentsPageSubheading', array('htmlTag' => 'h4', 'htmlClass' => 'sub_heading'));
				$topicHeading = new Docx\Style('1TopicHeading', array('htmlTag' => 'h2', 'htmlClass' => 'topic_heading', 'addHtmlId' => true));
				$subHeading1 = new Docx\Style('2SubHeading1', array('htmlTag' => 'h4', 'htmlClass' => 'sub_heading_1', 'addHtmlId' => true, 'passUnderNextStyle' => 'topic_heading'));
				$subHeading2 = new Docx\Style('3SubHeading2', array('htmlTag' => 'h4', 'htmlClass' => 'sub_heading_2'));
				$subHeading3 = new Docx\Style('4SubHeading3', array('htmlTag' => 'h4', 'htmlClass' => 'sub_heading_3'));
				$firstLevelBullet = new Docx\Style('8FirstLevelBullet', array('listLevel' => 1));
				$firstLevelBulletItalic = new Docx\Style('9FirstLevelBulletItalic', array('htmlClass' => 'italic', 'listLevel' => 1));
				$firstLevelBulletBold = new Docx\Style('10FirstLevelBulletBold', array('htmlClass' => 'bold', 'listLevel' => 1));
				$secondLevelBullet = new Docx\Style('11SecondLevelBullet', array('listLevel' => 2));
				$secondLevelBulletItalic = new Docx\Style('12SecondLevelBulletItalic', array('htmlClass' => 'italic', 'listLevel' => 2));
				$secondLevelBulletBold = new Docx\Style('13SecondLevelBulletBold', array('htmlClass' => 'bold', 'listLevel' => 2));
				
				# Parse
				$parser = new Docx\Docx($fileUri, $fileName);
				$parser::$storageLinkClass = 'subtopic_link';
				$parser->import()
					->attachStyles($chapterHeading, $contentsTopicHeader, $contentsPageSubHeader, $topicHeading, $subHeading1, $subHeading2, $subHeading3, $bodyCopy, $bodyCopyItalic, $bodyCopyBold)
					->attachStyles($firstLevelBullet, $firstLevelBulletItalic, $firstLevelBulletBold, $secondLevelBullet, $secondLevelBulletItalic, $secondLevelBulletBold)
					->getNodes()
					->parseLists()
					->parseNodes()
					->render()
				;
				echo $parser->html;
				
			#	var_dump($parser);
				
				$showForm = false;
			} else echo 'Docx file extension only';
		}
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