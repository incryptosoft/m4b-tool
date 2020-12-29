<?php


namespace M4bTool\Executables;


use Exception;
use M4bTool\Audio\Tag;
use M4bTool\Audio\Tag\TagWriterInterface;
use M4bTool\Common\Flags;
use SplFileInfo;
use Symfony\Component\Console\Helper\ProcessHelper;
use Symfony\Component\Console\Output\OutputInterface;

class Mp4art extends AbstractMp4v2Executable implements TagWriterInterface
{

    public function __construct($pathToBinary = "mp4art", ProcessHelper $processHelper = null, OutputInterface $output = null)
    {
        parent::__construct($pathToBinary, $processHelper, $output);
    }


    /**
     * @param SplFileInfo $file
     * @param Tag $tag
     * @param Flags|null $flags
     * @throws Exception
     */
    public function writeTag(SplFileInfo $file, Tag $tag, Flags $flags = null)
    {
        $this->removeAllCoversAndIgnoreErrors($file);

        if ($tag->hasCoverFile()) {
            if (!file_exists($tag->cover)) {
                throw new Exception(sprintf("Provided cover file does not exist: %s", $file));
            }
            $command = ["--add", $tag->cover, $file];
            // $this->appendParameterToCommand($command, "-f", $this->optForce);
            $process = $this->runProcess($command);

            if ($process->getExitCode() !== 0) {
                throw new Exception(sprintf("Could not add cover to file: %s, %s, %d", $file, $process->getOutput() . $process->getErrorOutput(), $process->getExitCode()));
            }
        }
    }

    private function removeAllCoversAndIgnoreErrors(SplFileInfo $file)
    {
        $command = ["--remove", "--art-any", $file];
        $this->runProcess($command);
    }

    /**
     * @param SplFileInfo $audioFile
     * @param SplFileInfo|null $destinationFile
     * @param int $index
     * @return SplFileInfo|null
     * @throws Exception
     */
    public function exportCover(SplFileInfo $audioFile, SplFileInfo $destinationFile = null, $index = 0)
    {
        $index = (int)$index;
        $this->runProcess([
            "--art-index", (string)$index,
            "--extract", $audioFile
        ]);

        $fileName = $audioFile->getBasename("." . $audioFile->getExtension());

        $extractedCoverPrefix = ltrim($audioFile->getPath() . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR) . $fileName . ".art[" . $index . "]";
        $extractedCoverCandidates = [
            new SplFileInfo($extractedCoverPrefix . ".jpg"),
            new SplFileInfo($extractedCoverPrefix . ".png"),
        ];

        $extractedCoverFile = null;
        foreach ($extractedCoverCandidates as $extractedCoverCandidate) {
            if ($extractedCoverCandidate->isFile()) {
                $extractedCoverFile = $extractedCoverCandidate;
                break;
            }
        }
        if ($extractedCoverFile === null) {
            throw new Exception(sprintf("exporting cover to %s failed", $extractedCoverFile));
        }

        if ($destinationFile === null) {
            return $extractedCoverFile;
        }

        if (!rename($extractedCoverFile, $destinationFile)) {
            @unlink($extractedCoverFile);
            throw new Exception(sprintf("renaming cover %s => %s failed", $extractedCoverFile, $destinationFile));
        }
        return $extractedCoverFile;
    }
}
