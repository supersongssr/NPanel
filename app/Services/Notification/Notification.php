<?php

namespace App\Services\Notification;

/**
 * 告警消息值对象 (不可变)
 *
 * @package App\Services\Notification
 */
class Notification
{
    /** @var int 级别 Level::* */
    private $level;
    /** @var string 标题 */
    private $title;
    /** @var string 内容 */
    private $content;
    /** @var array 附加上下文 (节点名/IP 等, 预留给格式化层) */
    private $context;

    public function __construct($level, $title, $content, array $context = [])
    {
        $this->level   = $level;
        $this->title   = $title;
        $this->content = $content;
        $this->context = $context;
    }

    /** @return int */
    public function getLevel() { return $this->level; }

    /** @return string */
    public function getTitle() { return $this->title; }

    /** @return string */
    public function getContent() { return $this->content; }

    /** @return array */
    public function getContext() { return $this->context; }
}
