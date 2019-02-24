Docx Parser
====

Recode branch - Work in progress. 

====

*Whats new?* 

* Refactored the architecture to be easier to maintain, and be properly OOP.
* All domElements are now treated equally, so the order is preserved in all cases.
* RenderMode is properly propogated throughout, so rendering to other formats is now better supported.
* Adding customised tag rendering easier to handle
* No more archaic arrays at the pre-process stage, so its easier to see how it works.
* Tidied up PHPDocs throughout
* Removed all dynamically set properties in all objects
* Proper priv/prot/public usage.

====

Progress: 
* [x] Paragraphs (basic text)
* [ ] Text attributes (bold, underline, italic, tabbed, sub & sup script) 
* [x] Images
* [x] Images - inline (new!)
* [ ] Lists
* [ ] Hyperlinks
* [ ] Tables
* [ ] Word Styles
* [ ] Textboxes


====


Requirements:

- PHP >= 5.4 supported
- /tmp writable by PHP