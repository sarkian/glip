<?php
/*
 * Copyright (C) 2008, 2009 Patrik Fimml
 *
 * This file is part of glip.
 *
 * glip is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.

 * glip is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with glip.  If not, see <http://www.gnu.org/licenses/>.
 */



class Glip_GitCommit extends Glip_GitObject
{
    /**
     * @var string The tree referenced by this commit, as binary sha1
     * string.
     */
    public $tree;

    /**
     * @var string[] Parent commits of this commit, as binary sha1
     * strings.
     */
    public $parents;

    /**
     * @var Glip_GitCommitStamp The author of this commit.
     */
    public $author;

    /**
     * @var Glip_GitCommitStamp The committer of this commit.
     */
    public $committer;

    /**
     * @var string Commit summary, i.e. the first line of the commit message.
     */
    public $summary;

    /**
     * @var string Everything after the first line of the commit message.
     */
    public $detail;

    public $history;

    public function __construct($repo)
    {
        parent::__construct($repo, Glip_Git::OBJ_COMMIT);
    }

    public function _unserialize($data)
    {
        $lines = explode("\n", $data);
        unset($data);
        $meta = array('parent' => array());
        while(($line = array_shift($lines)) != '') {
            $parts = explode(' ', $line, 2);
            if(!isset($meta[$parts[0]]))
                $meta[$parts[0]] = array($parts[1]);
            else
                $meta[$parts[0]][] = $parts[1];
        }

        $this->tree = Glip_Binary::sha1_bin($meta['tree'][0]);
        $this->parents = array_map(['Glip_Binary', 'sha1_bin'], $meta['parent']);
        $this->author = new Glip_GitCommitStamp;
        $this->author->unserialize($meta['author'][0]);
        $this->committer = new Glip_GitCommitStamp;
        $this->committer->unserialize($meta['committer'][0]);

        $this->summary = array_shift($lines);
        $this->detail = implode("\n", $lines);

        $this->history = null;
    }

    public function _serialize()
    {
        $s = '';
        $s .= sprintf("tree %s\n", Glip_Binary::sha1_hex($this->tree));
        foreach($this->parents as $parent)
            $s .= sprintf("parent %s\n", Glip_Binary::sha1_hex($parent));
        $s .= sprintf("author %s\n", $this->author->serialize());
        $s .= sprintf("committer %s\n", $this->committer->serialize());
        $s .= "\n".$this->summary."\n".$this->detail;
        return $s;
    }

    /**
     * @brief Get commit history in topological order.
     * @returns Glip_GitCommit[]
     */
    public function getHistory()
    {
        if($this->history)
            return $this->history;

        /* count incoming edges */
        $inc = array();

        $queue = array($this);
        while(($commit = array_shift($queue)) !== null) {
            foreach($commit->parents as $parent) {
                if(!isset($inc[$parent])) {
                    $inc[$parent] = 1;
                    $queue[] = $this->repo->getObject($parent);
                } else
                    $inc[$parent]++;
            }
        }

        $queue = array($this);
        $r = array();
        while(($commit = array_pop($queue)) !== null) {
            array_unshift($r, $commit);
            foreach($commit->parents as $parent) {
                if(--$inc[$parent] == 0)
                    $queue[] = $this->repo->getObject($parent);
            }
        }

        $this->history = $r;
        return $r;
    }

    /**
     * @brief Get the tree referenced by this commit.
     * @returns Glip_GitTree referenced by this commit.
     */
    public function getTree()
    {
        return $this->repo->getObject($this->tree);
    }

    /**
     * @copybrief Glip_GitTree::find()
     * This is a convenience function calling Glip_GitTree::find() on the commit's
     * tree.
     * @copydetails Glip_GitTree::find()
     */
    public function find($path)
    {
        return $this->getTree()->find($path);
    }

    /**
     * @param Glip_GitCommit $a
     * @param Glip_GitCommit $b
     * @return array
     */
    static public function treeDiff($a, $b)
    {
        return Glip_GitTree::treeDiff($a ? $a->getTree() : null, $b ? $b->getTree() : null);
    }
}

