<?php
/**
 * Create an ePub compatible book file.
 *
 * Once finalized a book can no longer have chapters of data added or changed.
 *
 * License: GNU LGPL.
 *
 * @author Grandt
 * @depend Zip.php http://www.phpclasses.org/browse/package/6110.html
 */
class EPub {

	private $zip;
	private $zipData;

	private $title = "";
	private $language = "en";
	private $identifier = "";
	private $identifierType = "";
	private $description = "";
	// Added by Martin Fenner
	private $subject = "";
	private $author = "";
	private $authorSortKey = "";
	private $publisherName = "";
	private $publisherURL = "";
	private $date = 0;
	private $rights = "";
	private $sourceURL = "";
	
	private $chapterCount = 0;
	private $opf_manifest = "";
	private $opf_spine = "";
	private $ncx_navmap = "";
	private $isFinalized = false;
	private $ignoreEmptyBuffer = false;

	private $dateformat = 'Y-m-d\TH:i:s.000000P'; // ISO 8601 long
	private $dateformatShort = 'Y-m-d'; // short date format to placate ePubChecker.
	private $headerDateFormat = "D, d M Y H:i:s T";

	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	function __construct() {
		include_once("Zip.php");

		$this->zip = new Zip();
		$this->zip->addFile("application/epub+zip", "mimetype");
		$this->zip->addDirectory("META-INF/");

		$this->content = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
			. "<container version=\"1.0\" xmlns=\"urn:oasis:names:tc:opendocument:xmlns:container\">\n"
			. "\t<rootfiles>\n"
			. "\t\t<rootfile full-path=\"book.opf\" media-type=\"application/oebps-package+xml\" />\n"
			. "\t</rootfiles>\n"
			. "</container>\n";
		$this->zip->addFile($this->content, "META-INF/container.xml");
		$this->content = null;
		$this->opf_manifest = "\t\t<item id=\"ncx\" href=\"book.ncx\" media-type=\"application/x-dtbncx+xml\" />\n";
		$this->chapterCount = 0;
	}

	/**
	 * Class destructor
	 *
	 * @return void
	 */
	function __destruct() {
		$this->zip = null;
		$this->title = "";
		$this->author = "";
		$this->publisher = "";
		$this->publishDate = 0;
		$this->bookId = "";
		$this->opf_manifest = "";
		$this->opf_spine = "";
		$this->ncx_navmap = "";
		$this->chapterCount = 0;
	}

	/**
	 *
	 * @param String $fileName Filename to use for the file, must be unique for the book.
	 * @param String $fileId Unique identifier for the file.
	 * @param String $fileData File data
	 * @param String $mimetype file mime type
	 * @return void
	 */
	function addFile($fileName, $fileId,  $fileData, $mimetype) {
		if ($this->isFinalized) {
			return;
		}
		$this->zip->addFile($fileData, $fileName);
		$this->opf_manifest .= "\t\t<item id=\"" . $fileId . "\" href=\"" . $fileName . "\" media-type=\"" . $mimetype . "\" />\n";
	}

	/**
	 *
	 * @param String $fileName Filename to use for the CSS file, must be unique for the book.
	 * @param String $fileId Unique identifier for the file.
	 * @param String $fileData CSS data
	 * @return void
	 */
	function addCSSFile($fileName, $fileId,  $fileData) {
		if ($this->isFinalized) {
			return;
		}
		$this->zip->addFile($fileData, $fileName);
		$this->opf_manifest .= "\t\t<item id=\"" . $fileId . "\" href=\"" . $fileName . "\" media-type=\"text/css\" />\n";
	}
	
	/**
	 * Add a cover to the book. Requires the PHP GD library.
	 *
	 * @param String $chapterName Name of the chapter, will be use din the TOC
	 * @param String $fileName Filename to use for the chapter, must be unique for the book.
	 * @param String $chapter Chapter text in XHTML or array $chapterData valid XHTML data for the chapter. File should NOT exceed 250kB.
	 * @param Bool   $autoSplit should the chapter be split if it exceeds 250kB? Default=false, only used if $chapterData is a String.
	 * @return void
	 */
  function addCover($title, $author, $publisher_name) {
		if ($this->isFinalized) {
			return;
		}
		// This function requires the gd library
		if (!extension_loaded('gd') || !function_exists('imageTTFText')) {
			// Add title page as fallback mechanism
			$chapterName = "Title Page";
			$fileName = "preface.xhtml";
			$title_content = "<h1>" . $title . "</h1>\n"
			 . "<footer>\n<h3 class=\"author\">" . $author . "</h3>\n"
			 . "<p class=\"publisher\">" . $publisher_name . "</p>\n</footer>";
			$this->addChapter($chapterName, $fileName, $title_content);
		} else {
			$fname = ABSPATH . 'wp-content/plugins/epub-export/cover.png';
			$image = imagecreatefrompng($fname);
			$title_font = ABSPATH . 'wp-content/plugins/epub-export/fonts/STIXGeneralBol.ttf';
			$author_font = ABSPATH . 'wp-content/plugins/epub-export/fonts/STIXGeneral.ttf';
			$publisher_font = ABSPATH . 'wp-content/plugins/epub-export/fonts/STIXGeneral.ttf';
			$title_size = 60;
			$author_size = 48;
			$publisher_size = 36;
			$title_color = imagecolorallocate($image, 0,0,128);
			$author_color = imagecolorallocate($image, 0,0,0);
			$publisher_color = imagecolorallocate($image, 51,51,51);
		
			// Word wrap for title
			$lines = explode('|', wordwrap($title, 18, '|'));
			$y = 200;
			foreach ($lines as $line)
			{
			  imagettftext($image, $title_size, 0, 100, $y, $title_color, $title_font, $line);
			  $y += 90;
			}
			imageTTFText($image, $author_size, 0, 100, 700, $author_color, $author_font, $author);
			imageTTFText($image, $publisher_size, 0, 100, 1700, $publisher_color, $publisher_font, $publisher_name);
		
			$new_fname = "cover_with_text.png";
			imagepng($image, $new_fname, 0, NULL);
		  $fileData = file_get_contents($new_fname);
		  $fname = "images/" . basename($fname);
		  $this->addFile($fname, "cover-image", $fileData, "image/png");

			$chapterName = "Cover";
			$fileName = "cover.xhtml";
			$cover_content = "<div><img src=\"images/cover.png\" /></div>";
			$this->addChapter($chapterName, $fileName, $cover_content, true);
		}
	}

	/**
	 * Add a chapter to the book, as a chapter should not exceed 250kB, you can parse an array with multiple parts as $chapterData.
	 * These will still only show up as a single chapter in the book TOC.
	 *
	 * @param String $chapterName Name of the chapter, will be use din the TOC
	 * @param String $fileName Filename to use for the chapter, must be unique for the book.
	 * @param String $chapter Chapter text in XHTML or array $chapterData valid XHTML data for the chapter. File should NOT exceed 250kB.
	 * @param Bool   $autoSplit should the chapter be split if it exceeds 250kB? Default=false, only used if $chapterData is a String.
	 * @return void
	 */
	function addChapter($chapterName, $fileName, $chapterData, $autoSplit = false, $notLinear = false) {
		if ($this->isFinalized) {
			return;
		}
		
		// Add HTML 1.1 header and footer. Added by Martin Fenner
		$header = 
			"<?xml version=\"1.0\" encoding=\"utf-8\"?>\n"
			. "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.1//EN\"\n"
			. "    \"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd\">\n"
			. "<html xmlns=\"http://www.w3.org/1999/xhtml\">\n"
			. "<head>\n"
			. "<meta http-equiv=\"Content-Type\" content=\"application/xhtml+xml; charset=utf-8\" />\n"
			. "<link rel=\"stylesheet\" type=\"text/css\" href=\"epub.css\" />\n"
			. "<title>" . $this->title . "</title>\n"
			. "</head>\n"
			. "<body>\n";
			
		$footer =
		  "\n</body>\n"
		  . "</html>";

		$chapter = $chapterData;
		if (is_string($chapterData) && strlen($chapterData) > 250000 && $autoSplit) {
			include_once("EPubChapterSplitter.php");
			$splitter = new EPubChapterSplitter();

			$chapterArray = $splitter->splitChapter($chapterData);
			if (count($chapterArray) > 1) {
				$chapter = $chapterArray;
			}
		}

		if (is_string($chapter) && strlen($chapter) > 0) {
      // Add HTML 1.1 header and footer. Added by Martin Fenner
			$chapter = $header . $chapter . $footer;
			$this->zip->addFile($chapter, $fileName);
			$this->chapterCount++;
			$this->opf_manifest .= "\t\t<item id=\"chapter" . $this->chapterCount . "\" href=\"" . $fileName . "\" media-type=\"application/xhtml+xml\" />\n";
      $linear_text = ""; 
      // Set linear = "no" for cover image     
      if ($notLinear)
        $linear_text = " linear = \"no\"";
			$this->opf_spine .= "\t\t<itemref idref=\"chapter" . $this->chapterCount . "\"" . $linear_text . " />\n";

			$this->ncx_navmap .= "\n\t\t<navPoint id=\"chapter" . $this->chapterCount . "\" playOrder=\"" . $this->chapterCount . "\">\n"
				. "\t\t\t<navLabel><text>" . $chapterName . "</text></navLabel>\n"
				. "\t\t\t<content src=\"" . $fileName . "\" />\n"
				. "\t\t</navPoint>\n";
		} else if (is_array($chapter)) {
			$partCount = 0;
			$this->chapterCount++;
			while (list($k, $v) = each($chapter)) {
				$partCount++;
				// Add HTML 1.1 header and footer. Added by Martin Fenner
				$v = $header . $v . $footer;
				$this->zip->addFile($v, $fileName . "-" . $partCount);

				$this->opf_manifest .= "\t\t<item id=\"chapter" . $this->chapterCount . "-" . $partCount . "\" href=\"" . $fileName  . "-" . $partCount . "\" media-type=\"application/xhtml+xml\" />\n";

				$this->opf_spine .= "\t\t<itemref idref=\"chapter" . $this->chapterCount . "-" . $partCount . "\" />\n";
			}

			$this->ncx_navmap .= "\n\t\t<navPoint id=\"chapter" . $this->chapterCount . "-1\" playOrder=\"" . $this->chapterCount . "\">\n"
				. "\t\t\t<navLabel><text>" . $chapterName . "</text></navLabel>\n"
				. "\t\t\t<content src=\"" . $fileName . "-1\" />\n"
				. "\t\t</navPoint>\n";
		}
	}

	/**
	 * Book title, mandatory.
	 *
	 * Used for the dc:title metadata parameter in the OPF file as well as the DocTitle attribute in the NCX file.
	 *
	 * @param string $title
	 * @access public
	 * @return void
	 */
	function setTitle($title) {
		if ($this->isFinalized) {
			return;
		}
		$this->title = $title;
	}

	/**
	 * Book language, mandatory
	 *
	 * Use the RFC3066 Language codes, such as "en", "da", "fr" etc.
	 * Defaults to "en".
	 *
	 * Used for the dc:language metadata parameter in the OPF file.
	 *
	 * @param string $language
	 * @access public
	 * @return void
	 */
	function setLanguage($language) {
		if ($this->isFinalized || strlen($language) != 2) {
			return;
		}
		$this->language = $language;
	}

	/**
	 * Unique book identifier, mandatory.
	 * Use the URI, or ISBN if available.
	 *
	 * Used for the dc:identifier metadata parameter in the OPF file, as well as dtb:uid in the NCX file.
	 *
	 * Identifier type should only be "URI", "ISBN" and "UUID".
	 *
	 * @param string $identifier
	 * @param string $identifierType
	 * @access public
	 * @return void
	 */
	function setIdentifier($identifier, $identifierType) {
		if ($this->isFinalized || ($identifierType != "URI" && $identifierType != "ISBN" && $identifierType != "UUID")) {
			return;
		}
		$this->identifier = $identifier;
		$this->identifierType = $identifierType;
	}

	/**
	 * Book description, optional.
	 *
	 * Used for the dc:description metadata parameter in the OPF file
	 *
	 * @param string $description
	 * @access public
	 * @return void
	 */
	function setDescription($description) {
		if ($this->isFinalized) {
			return;
		}
		$this->description = $description;
	}
	
	/**
	 * Book subject, optional. Added by Martin Fenner
	 *
	 * Used for the dc:source metadata parameter in the OPF file
	 *
	 * @param string $subject
	 * @access public
	 * @return void
	 */
	function setSubject($subject) {
		if ($this->isFinalized) {
			return;
		}
		$this->subject = $subject;
	}

	/**
	 * Book author or creator, optional.
	 * The $authorSortKey is basically how the name is to be sorted, usually it's "Lastname, First names"
	 * where the $author is the straight "Firstnames Lastname"
	 *
	 * Used for the dc:creator metadata parameter in the OPF file and the docAuthor attribure in the NCX file.
	 * The sort key is used for the opf:file-as attribute in dc:creator.
	 *
	 * @param string $author
	 * @param string $authorSortKey
	 * @access public
	 * @return void
	 */
	function setAuthor($author, $authorSortKey) {
		if ($this->isFinalized) {
			return;
		}
		$this->author = $author;
		$this->authorSortKey = $authorSortKey;
	}

	/**
	 * Publisher Information, optional.
	 *
	 * Used for the dc:publisher and dc:relation metadata parameters in the OPF file.
	 *
	 * @param string $publisherName
	 * @param string $publisherURL
	 * @access public
	 * @return void
	 */
	function setPublisher($publisherName, $publisherURL) {
		if ($this->isFinalized) {
			return;
		}
		$this->publisherName = $publisherName;
		$this->publisherURL = $publisherURL;
	}

	/**
	 * Release date, optional. If left blank, the time of the finalization will be used.
	 *
	 * Used for the dc:date metadata parameter in the OPF file
	 *
	 * @param long $timestamp
	 * @access public
	 * @return void
	 */
	function setDate($timestamp) {
		if ($this->isFinalized) {
			return;
		}
		$this->date = $timestamp;
	}

	/**
	 * Book (copy)rights, optional.
	 *
	 * Used for the dc:rights metadata parameter in the OPF file
	 *
	 * @param string $rightsText
	 * @access public
	 * @return void
	 */
	function setRights($rightsText) {
		if ($this->isFinalized) {
			return;
		}
		$this->rights = $rightsText;
	}

	/**
	 * Book source URL, optional.
	 *
	 * Used for the dc:source metadata parameter in the OPF file
	 *
	 * @param string $sourceURL
	 * @access public
	 * @return void
	 */
	function setSourceURL($sourceURL) {
		if ($this->isFinalized) {
			return;
		}
		$this->sourceURL = $sourceURL;
	}

	/**
	 * Set ePub date formate to the short yyyy-mm-dd form, for compliance with a bug in ePubChecker.
	 *
	 * @access public
	 * @return void
	 */
	function setShortDateFormat() {
		if ($this->isFinalized) {
			return;
		}
		$this->dateformat = $this->dateformatShort;
	}

	/**
	 * Should EPub ignore an active Output buffer as long as it does not contain data?.
	 *
	 * @param ignoreEmptyBuffer boolean Any input is valid, only true will set output buffer clearing.
	 * @access public
	 * @return void
	 */
	function setIgnoreEmptyBuffer($ignoreEmptyBuffer = true) {
		if ($this->isFinalized) {
			return;
		}

		$this->ignoreEmptyBuffer = ($ignoreEmptyBuffer === true);
	}

	/**
	 * Check for mandatory parameters and finalize the e-book.
	 * Once finalized, the book is locked for further additions.
	 *
	 * @return unknown_type
	 */
	function finalize() {
		if ($this->isFinalized || $this->chapterCount == 0 || strlen($this->title) == 0 || strlen($this->language) == 0 || strlen($this->identifier) == 0 || strlen($this->identifierType) == 0) {
			return;
		}

		if ($this->date == 0) {
			$this->date = time();
		}

		// Generate OPF data:
		$this->opf = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n"
			. "<package xmlns=\"http://www.idpf.org/2007/opf\" unique-identifier=\"BookId\" version=\"2.0\">\n"
			. "\t<metadata xmlns:dc=\"http://purl.org/dc/elements/1.1/\"\n"
			. "\t\txmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"\n"
			. "\t\txmlns:opf=\"http://www.idpf.org/2007/opf\" \n"
			. "\t\txmlns:dcterms=\"http://purl.org/dc/terms/\">\n"
			. "\t\t<dc:title>" . $this->title . "</dc:title>\n"
			. "\t\t<dc:language>" . $this->language . "</dc:language>\n"
			. "\t\t<dc:identifier id=\"BookId\" opf:scheme=\"" . $this->identifierType . "\">" . $this->identifier . "</dc:identifier>\n";
			
		if (strlen($this->description) > 0) {
			$this->opf .= "\t\t<dc:description>" . $this->description . "</dc:description>\n";
		}
		
		// Added by Martin Fenner
		if (strlen($this->subject) > 0) {
			$this->opf .= "\t\t<dc:subject>" . $this->subject . "</dc:subject>\n";
		}
			
		if (strlen($this->publisherName) > 0) {
			$this->opf .= "\t\t<dc:publisher>" . $this->publisherName . "</dc:publisher>\n";
		}
			
		if (strlen($this->publisherURL) > 0) {
			$this->opf .= "\t\t<dc:relation>" . $this->publisherURL . "</dc:relation>\n";
		}

		if (strlen($this->author) > 0) {
			$this->opf .= "\t\t<dc:creator";
			if (strlen($this->authorSortKey) > 0) {
				$this->opf .= " opf:file-as=\"" . $this->authorSortKey . "\"";
			}
			$this->opf .= " opf:role=\"aut\">" . $this->author . "</dc:creator>\n";
		}

		$this->opf .= "\t\t<dc:date>" . date($this->dateformat, $this->date) . "</dc:date>\n";
			
		if (strlen($this->rights) > 0) {
			$this->opf .= "\t\t<dc:rights>" . $this->rights . "</dc:rights>\n";
		}

		if (strlen($this->sourceURL) > 0) {
			$this->opf .=  "\t\t<dc:source>" . $this->sourceURL . "</dc:source>\n";
		}
		
		// Add metadata for cover
		$this->opf .=  "\t\t<meta name=\"cover\" content=\"cover-image\"/>\n";
			
		$this->opf .= "\t</metadata>\n\n\t<manifest>\n" . $this->opf_manifest . "\t</manifest>\n\n\t<spine toc=\"ncx\">\n" . $this->opf_spine . "\t</spine>\n</package>\n";

		$this->ncx = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
			. "<!DOCTYPE ncx PUBLIC \"-//NISO//DTD ncx 2005-1//EN\"\n"
			. "   \"http://www.daisy.org/z3986/2005/ncx-2005-1.dtd\">\n"
			. "<ncx xmlns=\"http://www.daisy.org/z3986/2005/ncx/\" version=\"2005-1\" xml:lang=\"en\">\n"
			. "\t<head>\n"
			. "\t\t<meta name=\"dtb:uid\" content=\"" . $this->identifier . "\" />\n"
			. "\t\t<meta name=\"dtb:depth\" content=\"2\" />\n"
			. "\t\t<meta name=\"dtb:totalPageCount\" content=\"0\" />\n"
			. "\t\t<meta name=\"dtb:maxPageNumber\" content=\"0\" />\n"
			. "\t</head>\n\n\t<docTitle>\n\t\t<text>" . $this->title . "</text>\n\t</docTitle>\n\n";

		if (strlen($this->author) > 0) {
			$this->ncx .= "\t<docAuthor>\n\t\t<text>" . $this->author . "</text>\n\t</docAuthor>\n\n";
		}

		$this->ncx .= "\t<navMap>\n" . $this->ncx_navmap . "\t</navMap>\n</ncx>\n";

		$this->zip->addFile($this->opf, "book.opf");
		$this->zip->addFile($this->ncx, "book.ncx");
		$this->opf = "";
		$this->ncx = "";

		$this->zipData = $this->zip->getZipData();
		$this->zip = null;
		$this->isFinalized = true;
	}
	
	/**
	 * Extract images from HTML and save them as separate files. Added by Martin Fenner.
	 *
	 * @return String
	 */
	function extractImages($chapter) {
		$doc=new DOMDocument();
		$doc->loadHTML($chapter);
		$xml=simplexml_import_dom($doc);
		$images=$xml->xpath('//img');
		foreach ($images as $img) {
			$fileName = $img['src'];
			$fileName = str_replace('"', '', $fileName);
			$imageSize = getimagesize($fileName); 
			$mime = $imageSize['mime'];
			$fileData = file_get_contents($fileName);
			$fileName = "images/" . basename($fileName);
			$chapter = str_replace($img['src'], $fileName, $chapter);
			$this->addFile($fileName, uniqid(),  $fileData, $mime);
		}
		return $chapter;
	}

	/**
	 * Return the finalized book.
	 *
	 * @return String
	 */
	function getBook() {
		if ($this->isFinalized) {
			return $this->zipData;
		}

		return "";
	}

	/**
	 * Send the book as a zip download
	 *
	 * Sending will fail if the output buffer is in use. You can override this limit by
	 *  calling setIgnoreEmptyBuffer(true), though the function will still fail if that
	 *  buffer is not empty.
	 *
	 * @param String $fileName The name of the book without the .epub at the end.
	 * @return void
	 */
	function sendBook($fileName) {
		if (!$this->isFinalized) {
			return;
		}

		if (!headers_sent($headerFile, $headerLine) or die("<p><strong>Error:</strong> Unable to send file <strong>$fileName.epub</strong>. HTML Headers have already been sent from <strong>$headerFile</strong> in line <strong>$headerLine</strong></p>")) {
			if ((ob_get_contents() === false || ($this->ignoreEmptyBuffer && ob_get_contents() == '')) or die("<p><strong>Error:</strong> Unable to send file <strong>$fileName.epub</strong>. Output buffer initialized" . ($this->ignoreEmptyBuffer ? " and contains data" : "") . ".</p>")) {
				if (ini_get('zlib.output_compression')) {
					ini_set('zlib.output_compression', 'Off');
				}

				$this->length = strlen($this->zipData);
				header('Pragma: public');
				header("Last-Modified: " . date($this->headerDateFormat, $this->date));
				header("Expires: 0");
				header("Accept-Ranges: bytes");
				header("Connection: close");
				header("Content-Type: application/epub+zip");
				header('Content-Disposition: attachment; filename="' . $fileName . '.epub";' );
				header("Content-Transfer-Encoding: binary");
				header("Content-Length: ". $this->length);
				echo $this->zipData;
			}
		}
	}
	
	/**
	 * Generates an XHTML 1.1 header
	 *
	 * @author     Martin Fenner
	 * @return     string  the formatted header
	 */
	function setHeader() {
		$header = 
			"<?xml version=\"1.0\" encoding=\"utf-8\"?>\n"
			. "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.1//EN\"\n"
			. "    \"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd\">\n"
			. "<html xmlns=\"http://www.w3.org/1999/xhtml\">\n"
			. "<head>\n"
			. "<meta http-equiv=\"Content-Type\" content=\"application/xhtml+xml; charset=utf-8\" />\n"
			. "<link rel=\"stylesheet\" type=\"text/css\" href=\"epub.css\" />\n"
			. "<title>" . $this->title . "</title>\n"
			. "</head>\n"
			. "<body>\n";
			
		$this->header = $header;
	}
	
	/**
	 * Generates an XHTML footer
	 *
	 * @author     Martin Fenner
	 * @return     string  the formatted footer
	 */
	function setFooter() {
		$footer =
		  "</body>\n"
		  . "</html>";
		
		$this->footer = $footer;
	}

	/**
	 * Generates an UUID
	 * Added for convinience
	 *
	 * @author     Anis uddin Ahmad <admin@ajaxray.com>
	 * @return     string  the formatted uuid
	 */
	function createUUID($seed = 0) {
		$uid;
		if ($seed === 0) {
			$uid = md5(uniqid('', true));
		} else if ($seed === 1) {
			if ($this->isFinalized) {
				$uid = md5($this->zipData);
			} else {
				$uid = md5($this->zip->getZipData());
			}
		} else {
			$uid = md5($seed);
		}
		return substr($uid,0,8) . '-'
			. substr($uid,8,4) . '-'
			. substr($uid,12,4) . '-'
			. substr($uid,16,4) . '-'
			. substr($uid,20,12);
	}
}
?>