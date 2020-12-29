<?php


namespace M4bTool\Audio\Tag;

use M4bTool\Audio\Tag;
use M4bTool\Common\ReleaseDate;
use SimpleXMLElement;
use SplFileInfo;
use Throwable;

class OpenPackagingFormat extends AbstractTagImprover
{
    const DEFAULT_FILENAME = "metadata.opf";

    const NAMESPACE_DUBLIN_CORE = "dc";
    const NAMESPACE_OPEN_PACKAGING_FORMAT = "opf";

    const CREATOR_ROLE_AUTHOR = "aut";

    const META_NAME_CALIBRE_SERIES = "calibre:series";
    const META_NAME_CALIBRE_SERIES_INDEX = "calibre:series_index";
//    const META_NAME_CALIBRE_RATING = "calibre:rating";
//    const META_NAME_CALIBRE_TIMESTAMP = "calibre:timestamp";
    const META_NAME_CALIBRE_TITLE_SORT = "calibre:title_sort";

    protected $xmlString;

    public function __construct($xmlString = "")
    {
        $this->xmlString = $xmlString;
    }

    /**
     * Cover constructor.
     * @param SplFileInfo $reference
     * @param null $fileName
     * @return OpenPackagingFormat
     */
    public static function fromFile(SplFileInfo $reference, $fileName = null)
    {
        $fileToLoad = static::searchExistingMetaFile($reference, static::DEFAULT_FILENAME, $fileName);
        return $fileToLoad ? new static(file_get_contents($fileToLoad)) : new static();
    }

    /**
     * @param Tag $tag
     * @return Tag
     */
    public function improve(Tag $tag): Tag
    {

        if (trim($this->xmlString) === "") {
            $this->info(sprintf("%s not found - tags not improved", static::DEFAULT_FILENAME));
            return $tag;
        }
        try {
            $xml = simplexml_load_string($this->xmlString);
        } catch (Throwable $t) {
            return $tag;
        }

        $xml->registerXPathNamespace("opf", "http://www.idpf.org/2007/opf");
        $xml->registerXPathNamespace("dc", "http://purl.org/dc/elements/1.1/");

        $tag->title = $this->makeString($this->queryTag($xml, "title"));


        $description = $this->makeString($this->queryTag($xml, "description"));
        $tag->description = $tag->longDescription = $description ? strip_tags($description) : null;


        $creators = $xml->xpath("//dc:creator");
        foreach ($creators as $creator) {
            if ($this->queryAttribute($creator, "role", static::NAMESPACE_OPEN_PACKAGING_FORMAT) === static::CREATOR_ROLE_AUTHOR) {
                $author = $this->queryAttribute($creator, "file-as");
                if (strpos($author, ",")) {
                    $authorParts = explode(",", $author);
                    $authorParts[] = array_shift($authorParts);
                    $tag->artist = implode(" ", array_map("trim", $authorParts));
                } else {
                    $tag->artist = $this->makeString($creator);
                }
            }
        }


        $tag->year = ReleaseDate::createFromValidString($this->queryTag($xml, "date"));
        $tag->publisher = $this->makeString($this->queryTag($xml, "publisher"));
        $tag->language = $this->makeString($this->queryTag($xml, "language"));
        $tag->genre = $this->makeString($this->queryTag($xml, "subject"));


        $metas = ((array)$xml->metadata ?? ["meta" => []])["meta"] ?? [];
        foreach ($metas as $meta) {
            $name = $this->queryAttribute($meta, "name");
            $content = $this->queryAttribute($meta, "content");
            if ($content === "") {
                continue;
            }

            switch ($name) {
                case static::META_NAME_CALIBRE_SERIES:
                    $tag->series = $content;
                    break;
                case static::META_NAME_CALIBRE_SERIES_INDEX:
                    $tag->seriesPart = (int)$content;
                    break;
                case static::META_NAME_CALIBRE_TITLE_SORT:
                    // set sort title only if it differs from original title to
                    // support series based sorting, when titles match, but also
                    // custom sort titles, that do not match original title
                    if ($tag->title !== $content) {
                        $tag->sortTitle = $content;
                    }
                    break;
            }
        }

        return $tag;
    }

    private function queryTag(SimpleXMLElement $xml, $tagName, $namespace = self::NAMESPACE_DUBLIN_CORE)
    {
        if (isset($xml->metadata)) {
            return $xml->metadata->children($namespace, !!$namespace)->$tagName;
        }
        return null;
    }

    private function queryAttribute(SimpleXMLElement $xml, $attributeName, $namespace = null)
    {
        if ($namespace === null) {
            $attrs = $xml->attributes();
        } else {
            $attrs = $xml->attributes($namespace, true);
        }

        if (isset($attrs)) {
            return isset($attrs->$attributeName) ? (string)$attrs->$attributeName : null;
        }
        return null;
    }

    private function makeString($value)
    {
        if ((string)$value !== "") {
            return (string)$value;
        }
        return null;
    }

}
