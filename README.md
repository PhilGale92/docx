Docx Parser
====

This PHP based parser takes any docx file, and creates a PHP array containing its structure, content &amp; style information.
An HTML rendering class is included to demonstrate how you can then manipulate the array into different formats.

====

Supports:
- Word styles
- Paragraphs
- Text indentation / tabbing
- Nested lists (&amp; inline lists)
- Tables
- Images (&amp; finding the required image size)
- Hyperlinks
- Bold / Underlined / Italic text

====

Caveats:

- Images are displayed at the same dimensions as in word, however the included rendering class does not contain functionality for resizing the raw image files as required (due to it being out of scope for this project)
- The parser may take some time to run on larger documents
- Modifying text in word to look the same as a header style is not the same as setting its stylename to 'header'

====

Requirements:

- PHP 5.2 or greater
- /tmp writable by PHP
