Docx Parser
====

Recode branch - Work in progress. 

====

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

Progress: 
* [x] Paragraphs (basic text)
* [x] Text attributes (bold, underline, italic, tabbed, sub & sup script) 
* [x] Images
* [x] Images - inline (new!)
* [x] Lists
* [x] Hyperlinks
* [x] Tables (colspans, vertical merged cells etc)
* [ ] Word Styles
* [ ] Textboxes


====


Requirements:

- PHP >= 5.4 supported
- /tmp writable by PHP