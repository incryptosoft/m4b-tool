<?php


namespace M4bTool\Audio\Tag;


use Exception;
use M4bTool\Audio\BinaryWrapper;
use M4bTool\Audio\Tag;
use M4bTool\Chapter\ChapterHandler;
use Psr\Cache\InvalidArgumentException;
use Sandreas\Time\TimeUnit;

class AdjustTooLongChapters extends AbstractTagImprover
{
    /**
     * @var TimeUnit
     */
    protected $maxChapterLength;
    /**
     * @var TimeUnit
     */
    protected $desiredChapterLength;
    protected $file;

    /**
     * @var BinaryWrapper
     */
    protected $metaDataHandler;
    /**
     * @var ChapterHandler
     */
    protected $chapterHandler;
    /**
     * @var TimeUnit
     */
    protected $silenceLength;

    public function __construct(BinaryWrapper $metaDataHandler, ChapterHandler $chapterHandler, $file, $maxChapterLengthOriginalValue, TimeUnit $silenceLength)
    {
        $maxChapterLengthParts = explode(",", $maxChapterLengthOriginalValue);
        $desiredChapterLengthSeconds = $maxChapterLengthParts[0] ?? 0;
        $maxChapterLengthSeconds = $maxChapterLengthParts[1] ?? $desiredChapterLengthSeconds;

        $this->metaDataHandler = $metaDataHandler;
        $this->chapterHandler = $chapterHandler;
        $this->file = $file;
        $this->maxChapterLength = new TimeUnit((int)$maxChapterLengthSeconds, TimeUnit::SECOND);
        $this->desiredChapterLength = new TimeUnit((int)$desiredChapterLengthSeconds, TimeUnit::SECOND);
        $this->silenceLength = $silenceLength;

    }

    /**
     * @param Tag $tag
     * @return Tag
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function improve(Tag $tag): Tag
    {
        // at least one option has to be defined to adjust too long chapters
        if ($this->maxChapterLength->milliseconds() === 0 || !is_array($tag->chapters) || count($tag->chapters) === 0) {
            $this->info("no chapter length adjustment required (max chapter length not provided or empty chapter list)");
            return $tag;
        }

        if ($this->maxChapterLength->milliseconds() > 0) {
            $this->chapterHandler->setMaxLength($this->maxChapterLength);
            $this->chapterHandler->setDesiredLength($this->desiredChapterLength);
        }


        if (!$this->isAdjustmentRequired($tag)) {
            $this->info("no chapter length adjustment required (no too long chapters found)");
            return $tag;
        }
        $this->info(sprintf("adjusting %s chapters with max length %s and desired length %s", count($tag->chapters), $this->maxChapterLength->format(), $this->desiredChapterLength->format()));
        $totalLength = $this->metaDataHandler->inspectExactDuration($this->file);

        // fix last chapter length (e.g. when using a chapters.txt format where last chapter length is not given)
        if ($totalLength !== null) {
            $lastChapter = end($tag->chapters);
            $lastChapter->setEnd(new TimeUnit($totalLength->milliseconds()));
        }

        $silences = $this->metaDataHandler->detectSilences($this->file, $this->silenceLength);
        $tag->chapters = $this->chapterHandler->adjustChapters($tag->chapters, $silences);
        return $tag;
    }

    protected function isAdjustmentRequired(Tag $tag)
    {
        foreach ($tag->chapters as $chapter) {
            if ($chapter->getLength()->milliseconds() > $this->maxChapterLength->milliseconds()) {
                return true;
            }
        }
        return false;
    }
}
