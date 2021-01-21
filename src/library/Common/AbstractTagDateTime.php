<?php


namespace M4bTool\Common;


use DateTime;
use Exception;
use Throwable;

abstract class AbstractTagDateTime extends DateTime
{
    const ONLY_YEAR_LENGTH = 4;
    protected static $defaultFormatString = "Y/m/d";
    protected $formatString;

    /**
     * @param $string
     * @return static|null
     * @throws Exception
     */
    public static function createFromValidString($string)
    {
        if (!isset($string) || trim($string) === "") {
            return null;
        }
        $onlyYear = (strlen($string) === static::ONLY_YEAR_LENGTH);
        try {
            if (!$onlyYear) {
                return new static($string);
            }
            $return = new static($string . "-01-01");
            $return->setFormatString("Y");
            return $return;
        } catch (Throwable $t) {
            return null;
        }
    }

    public function setFormatString($formatString)
    {
        $this->formatString = $formatString;
    }

    public function __toString()
    {
        return $this->format($this->formatString ?? static::$defaultFormatString);
    }
}
