Minify
-------------------------------------------------------------------------------

About:

Minify (compress) and merge JavaScript and CSS files. If files are added with
includeJS or includeCSS or as cached headerData, they will be minified and
merged automatically.

Changes to files are recognized by modification time and size. Depending on
different inclusion of JS and CSS from extension templates or conditions,
multiple merged versions will be created.

This extension uses and bundles the Minify library from Google Code
(http://code.google.com/p/minify/) which is released under a New BSD License.
See their project site for more information.

Installation:

* Install the extension and include the static template

Configuration:

* All CSS and JS files that are included in the header via TypoScript or cached
  USER scripts are automatically minified and merged.

* Set plugin.tx_minify.enable = 0 to disable the minification for certain parts
  of the site.

* To skip the minification of certain files, add them with commas to
  plugin.tx_minify.skipFiles
