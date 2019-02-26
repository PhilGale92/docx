Docx Parser
====

**A PHP Based Docx Parser**


*Whats new?* 

* Refactored the architecture to be easier to maintain, and be properly OOP.
* Proper priv/prot/public usage.
* Removed all dynamically set properties in all objects
* All domElements are now treated equally, so the order is preserved in all cases.
* RenderMode is properly propagated throughout, so rendering to other formats is now better supported.
* Adding customised tag rendering easier to handle
* No more archaic arrays at the pre-process stage, so its easier to see how it works.
* Tidied up PHPDocs throughout


====

###USAGE ###

````php
    
/*
* Create the parse object which converts the file into internalised objects
*/
$parser = new PhilGale92Docx\Docx($absolutePathToDocxFile );

/*
* Now render the parser into html string  
*/
echo $parser
    ->render('html')
;
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
td.has_subcell  {padding-left:0;}
table table { width:100%; }
td td {
    height:72px;  
    border:none;
    border-bottom:1px solid black; 
    min-width:110px;
} 
td table tr:last-of-type td { border-bottom:0;}
.vmerge td {  }
span.indent { padding-left:36px;} 
````

====

Progress: 
* [x] Paragraphs (basic text)
* [x] Text attributes (bold, underline, italic, tabbed, sub & sup script) 
* [x] Images
* [x] Images - inline (new!)
* [x] Lists
* [x] Hyperlinks
* [x] Tables (colspans, vertical merged cells etc)
* [x] Composer support
* [ ] Word Styles
* [ ] Textboxes


====


Requirements:

- PHP >= 5.4 supported