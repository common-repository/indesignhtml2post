=== InDesign HTML 2 Post ===
Contributors: munger41
Tags: indesign, indesigntopost, indesign2post, to, post, 2, convert, html, export, automatic, html2post, htmltopost, multisite
Requires at least: 4.0
Tested up to: 5.7

Bulk convert HTML InDesign exports documents to posts (imports all text and images - and attach images automatically to newly created posts).

== Description ==

Works directly on InDesign HTML exports and convert zipped filed to WP post, by:

* extracting all text data form html maintaining titles hierarchy : h1, h2, etc...
* attach all images included in zip to the created post
* automatically add featured image
* create gallery inside post content from all images extracted

Be carefull, you NEED to have installed on your server the following:

* [ZipArchive](http://php.net/manual/fr/class.ziparchive.php "ZipArchive")

Works on multisite installs.

[>> Demonstration site <<](https://www.indesign2wordpress.com/convert-html-document-to-wordpress-post/ "Demonstration")

== Installation ==

### Easy ###

1. Search via plugins > add new.
2. Find the plugin listed and click activate.
3. Use the new submenu item in "Posts"

### Usage ###

1. Go to Posts>New Post From InDesign HTML Export
2. Choose file to upload : .zip containing a main html file and all article images
3. Selection all options you want before creating
3. Clic "Create" and wait for post creation :)

[>> Demonstration site <<](https://www.indesign2wordpress.com/convert-html-document-to-wordpress-post/ "Demonstration")

== Changelog ==

1.6.5 - Add messages on upload errors

1.6.4 - bug fix on attachment ID lookup

1.6.3 - more debug for attachments

1.6.2 - check input file size

1.6.1 - bugfix : successive inputs with identical images filenames

1.6.0 - manage multiple figure ranges with identical image numbers

1.5.1 - front form improvements

1.5.0 - swiper option introduced

1.4.2 - better demo shortcode

1.4.1 - exception catched if image resize fail

1.4.0 - new profile based convertion with Imagick if CMYK as input

1.3.1 - new demo shortcode and factorization

1.3.0 - shortcode added

1.2.1 - first version in prod

1.2.0 - tmp directory set under uploads/

1.1.0 - can insert images inside text body when mentionned

1.0.1 - tested against several zip packages

1.0.0 - First stable release.