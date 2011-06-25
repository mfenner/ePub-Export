<?php
/**
 * Split an HTML file into smaller html files, retaining the formatting and structure for the individual parts.
 * What this splitter does is using DOM to try and retain any formatting in the file, including rebuilding the DOM tree for subsequent parts.
 * Split size is considered max target size. The actual size is the result of an even split across the resulting files. 
 *
 * License: GNU LGPL.
 * @author Grandt
 */
class EPubChapterSplitter {
	private $splitDefaultSize = 250000;

	/**
	 * Set default chapter target size.
	 * Default is 250000 bytes, and minimum is 2048 bytes.
	 *
	 * @param $size
	 * @return void
	 */
	function setSplitSize($size) {
		$this->splitDefaultSize = (int)$size;
		if (size < 2048) {
			$this->splitDefaultSize = 2048; // Making the file smaller than 2k is not a good idea. Even 2k is ... questionable.
		}
	}

	/**
	 * Split $chapter into multiple parts.
	 *
	 * @param $chapter
	 * @return array with 1 or more parts
	 */
	function splitChapter($chapter) {
		$chapterData = array();

		if (strlen($chapter) <= $splitDefaultSize) {
			$chapterData[] = $chapter;
			return $chapterData;
		}

		$xmlDoc = new DOMDocument();
		$xmlDoc->loadHTML($chapter);

		$head = $xmlDoc->getElementsByTagName("head");
		$body = $xmlDoc->getElementsByTagName("body");

		$htmlPos = stripos($chapter, "<html");
		$htmlEndPos = stripos($chapter, ">", $htmlPos);
		$newXML = substr($chapter, 0, $htmlEndPos+1) . "\n</html>";
		$headerLength = strlen($newXML);

		$files = array();
		$domDepth = 0;
		$domPath = array();
		$domClonedPath = array();

		$curFile = $xmlDoc->createDocumentFragment();
		$files[] = $curFile;
		$curParent = $curFile;
		$curSize = 0;

		$bodyLen = strlen($xmlDoc->saveXML($body->item(0)));
		$headLen = strlen($xmlDoc->saveXML($head->item(0))) + $headerLength;

		$partSize = $this->splitDefaultSize - $headLen;

		if ($bodyLen > $partSize) {
			$parts = ceil($bodyLen / $partSize);
			$partSize = ($bodyLen / $parts)  - $headLen;
		}
				
		$node = $body->item(0)->firstChild;

		do {
			$nodeData = $xmlDoc->saveXML($node);
			$nodeLen = strlen($nodeData);

			if ($nodeLen > $partSize && $node->hasChildNodes()) {
				$domPath[] = $node;
				$domClonedPath[] = $node->cloneNode(false);
				$domDepth++;

				$node = $node->firstChild;
			}

			$node2 = $node->nextSibling;

			if ($node != null && $node->nodeName != "#text") {
				if ($curSize > 0 && $curSize + $nodeLen > $partSize) {
					$curFile = $xmlDoc->createDocumentFragment();
					$files[] = $curFile;
					$curParent = $curFile;
					if ($domDepth > 0) {
						reset($domPath);
						reset($domClonedPath);
						while (list($k, $v) = each($domClonedPath)) {
							$newParent = $v->cloneNode(false);
							$curParent->appendChild($newParent);
							$curParent = $newParent;
						}
					}
					$curSize = strlen($xmlDoc->saveXML($curFile));
				}
				$curParent->appendChild($node->cloneNode(true));
				$curSize += $nodeLen;
			}

			$node = $node2;
			while ($node == null && $domDepth > 0) {
				$domDepth--;
				$node = end($domPath)->nextSibling;
				array_pop($domPath);
				array_pop($domClonedPath);
				$curParent = $curParent->parentNode;
			}
		} while ($node != null);

		$curFile = null;
		$curSize = 0;

		$xml = new DOMDocument('1.0', $xmlDoc->xmlEncoding);
		$xml->lookupPrefix("http://www.w3.org/1999/xhtml");
		$xml->preserveWhiteSpace = false;
		$xml->formatOutput = true;

		for ($idx = 0; $idx < count($files); $idx++) {
			$xml2Doc = new DOMDocument('1.0', $xmlDoc->xmlEncoding);
			$xml2Doc->lookupPrefix("http://www.w3.org/1999/xhtml");
			$xml2Doc->loadXML($newXML);
			$html = $xml2Doc->getElementsByTagName("html")->item(0);
			$html->appendChild($xml2Doc->importNode($head->item(0), true));
			$body = $xml2Doc->createElement("body");
			$html->appendChild($body);
			$body->appendChild($xml2Doc->importNode($files[$idx], true));

			// force pretty printing and correct formatting, should not be needed, but it is.
			$xml->loadXML($xml2Doc->saveXML());
			$chapterData[] = $xml->saveXML();
		}

		return $chapterData;
	}
}
?>
