Docx Parser
====

**A PHP Based Docx Parser**


### Installation ###

Composer (cli): `composer require philgale92/docx:3.*`

Composer (file):  Add the following to your `composer.json` file: 
````
    "require": {
        "philgale92/docx": "3.*"
    }
````

Manual: Files within `src` follow the PSR-0 format.


### Supports ### 

* [x] Paragraphs (basic text)
* [x] Text attributes (bold, underline, italic, tabbed, sub & sup script) 
* [x] Images
* [x] Lists
* [x] Hyperlinks
* [x] Tables (colspans, vertical merged cells etc)
* [x] Composer support
* [x] Word Styles
* [x] Custom attribute loading (see usage)
* [ ] Textboxes


### USAGE ### 

````php
    
/*
* Create the parse object which converts the file into internalised objects
*/
$parser = new \PhilGale92Docx\Docx($absolutePathToDocxFile );

/*
 * Attach style info (if any)
 */
 $parser->addStyle(
     (new \PhilGale92Docx\Style())
     ->setStyleId('standardPara')
     ->setHtmlClass('custom')
     ->setHtmlTag('p') // 'p' is default behaviour 
 );


 /*
  * Here is an example of a MetaData attribute style 
  * (Lets pull out the titleStyle directly)
  */
$parser->addStyle(
    (new \PhilGale92Docx\Style())
    ->setStyleId('0TitleName')
    
    // By setting this as metaData, we can pull in 
    // any content where this style is used in a seperate call.
    // It also removes the content from the standard render 
    
    ->setIsMetaData(true)
    // By default the metaData is parsed as HTML
    // But if you need to get the literal plain text then we can do that too  
    ->setMetaDataRenderMode(\PhilGale92Docx\Docx::RENDER_MODE_PLAIN)
);

/*
 * Here is an example of a heading style
 */
$parser->addStyle(
    (new \PhilGale92Docx\Style())
    ->setStyleId('1HeadingStyle')
    ->setHtmlTag('h2')
    ->setHtmlClass('custom')
);

/*
* Here is an example where we want to wrap all adjacent styles
* of this name with a div
*/
$parser->addStyle(
    (new \PhilGale92Docx\Style())
    ->setStyleId('3Boxgreytint')
    ->setBoxSimilarSiblings(true) // enable boxing behaviour
    ->setBoxClassName('box-style-tint-grey') // class of wrapping div
);

/*
 * You can also create word styles that make text lists
 * this is in addition to the standard list detection
*/
$parser->addStyle(
    (new \PhilGale92Docx\Style())
    ->setStyleId('4Numberedlist')
    ->setListHtmlTag('ol') // Takes 'ul' or 'ol'. 'ul' is default behaviour 
    ->setListLevel(1) // the indentation level, must be > 0 
);

/*
 * Now parse the xml into internal objects
*/
$parser->parse(); // Optional, ran automatically by ->render() if not ran yet

/*
* Now render the parser into html string  
*/
echo $parser
    ->render(\PhilGale92Docx\Docx::RENDER_MODE_HTML)
;
/*
 * We can grab any metaData content after ->parse() is performed
 * ->getMetaData() runs ->parse() if not ran yet
*/
var_dump(
    $parser->getMetaData() // also takes $styleId of metaData as an argument
);

/*
 * Are there any styles we forgot to declare?
 */
var_dump($parser->getDetectedStyles());


````

### Recommended CSS ###  

Here is some basic css styling you can apply as a starting point.

````css
table {
    border-collapse:collapse;
} 
th {
    text-align: left;
    text-transform: none;
}
td, th { 
    vertical-align:top;
    background-clip:padding-box;
    border:1px solid #000000;
    color: #414042;
    height: 34px;
    padding-left: 6px;
    position: relative;
}
td.has_subcell  {
    padding-left:0;
}
table table {
    width:100%;
}
td td {
    height:72px;  
    border:none;
    border-bottom:1px solid black; 
    min-width:110px;
} 
td table tr:last-of-type td {
    border-bottom:0;
}
span.indent {
    padding-left:36px;
} 
````

====

### Requirements: ### 

- PHP >= 5.4 


====

### Whats new (v1->v2) ###

* Plugged into composer (psr-0)
* Refactored the architecture to be easier to maintain, and be properly OOP.
* Proper priv/prot/public usage.
* Removed all dynamically set properties in all objects
* All domElements are now treated equally, so the order is preserved in all cases.
* RenderMode is properly propagated throughout, so rendering to other formats is now better supported.
* Adding customised tag rendering easier to handle
* No more archaic arrays at the pre-process stage, so its easier to see how it works.
* Tidied up PHPDocs throughout
