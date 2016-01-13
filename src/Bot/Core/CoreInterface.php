<?php
namespace Bot\Core;

interface CoreInterface
{
    const MESSAGE = 'message';
    const ADDED = 'added';
    const KICKED = 'kicked';

    public function initSkypeConnection();

    public function poll($timeout = 100);

    public function send($targetId, $message);
}
