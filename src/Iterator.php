<?php
/**
 * Created by PhpStorm.
 * User: keywan
 * Date: 04.11.18
 * Time: 21:42
 */

namespace GitlabComposer;


class Iterator implements \Iterator
{
    protected $subject;
    protected $method;
    protected $params;

    protected $page = 1;
    protected $i = 0;
    protected $list;

    public function __construct($subject, $method, $param = null)
    {
        $this->subject = $subject;
        $this->method = $method;
        $this->params = $param ? [$param] : [];
    }

    public function current()
    {
        return $this->list[$this->i];
    }

    public function next()
    {
        $this->i++;
        if ($this->i >= 100) {
            $this->page++;
            $this->fetch();
        }
        $this->list[$this->i];
    }

    public function key()
    {
        $key = ($this->page * 100) + $this->i;
        return $key;
    }

    public function valid()
    {
        $valid = isset($this->list[$this->i]);
        return $valid;
    }

    public function rewind()
    {
        $this->page = 1;
        $this->fetch();

    }

    protected function fetch()
    {
        $params = $this->params;
        $params[] = ['page' => $this->page, 'per_page' => 100];
        $method = $this->method;
        $this->list = $this->subject->$method(...$params);
        $this->i = 0;
    }
}