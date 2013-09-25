Docx Parser
====

This PHP based parser takes any docx file, and creates a PHP array containing its structure, content &amp; style information.
An HTML rendering class is included to demonstrate how you can then manipulate the array into different formats.

====

Caveats:

- Images are output at their original resolution, only scaled down so you may want additional processing to resize the images
- The parser may take some time to run on larger documents
- Does not support textboxes as of milestone 2
- Setting a piece of text to the same size &amp; colour as a heading style, is not the same as setting its stylename to 'header'

====

Requirements:

- PHP 5.2 or greater
- /tmp writable by PHP
