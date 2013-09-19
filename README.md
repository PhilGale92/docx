Docx Parser
====

This PHP based parser takes any docx file, and creates an object containing all of its styled text, images, lists & tables.

You may also choose to use the extending WordRender class to simply output this all as pure html or see how you may want to access the parsed data.

====

Notes:

- Style support is experimental (but in progress), check out the WordRender class for information.
- Don't forget to bump up the maximum execution time, for large documents this may take a moment to run.
- Images are sometimes stored in word files at their original resolution rather than the scaled down sizes you will see in a word document. In other words an image that may seem to be 200*200px may infact be 600*600. This parser saves the scaled down image sizes and uses them to render out the final images but I recommend that you rescale the images using PHP to save download times.

====

Requirements:

- PHP 5.2 or greater
- /tmp writable by PHP
