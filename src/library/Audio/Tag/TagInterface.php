<?php


namespace M4bTool\Audio\Tag;


interface TagInterface
{
    const FLAG_FORCE = 1 << 0;
    const FLAG_DEBUG = 1 << 1;
    const FLAG_NO_CLEANUP = 1 << 2;
    const FLAG_ADJUST_FOR_IPOD = 1 << 3;
}
