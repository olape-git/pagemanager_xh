<?php

/**
 * Copyright 2011-2017 Christoph M. Becker
 *
 * This file is part of Pagemanager_XH.
 *
 * Pagemanager_XH is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pagemanager_XH is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Pagemanager_XH.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Pagemanager;

class JSONProcessor
{
    /**
     * @var string[]
     */
    private $contents;

    /**
     * @var string[]
     */
    private $newContents;

    /**
     * @var array[]
     */
    private $pageData;

    /**
     * @var int
     */
    private $level;

    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    private $title;

    /**
     * @var string
     */
    private $pdattrName;

    /**
     * @var bool
     */
    private $pdattr;

    /**
     * @var bool
     */
    private $mayRename;

    /**
     * @var PageDataRouter
     */
    private $pdRouter;

    /**
     * @param string[] $contents
     * @param string $pdattrName
     */
    public function __construct(array $contents, $pdattrName)
    {
        global $pd_router;

        $this->contents = $contents;
        $this->pdattrName = $pdattrName;
        $this->pdRouter = $pd_router;
    }

    /**
     * @param string $json
     */
    public function process($json)
    {
        $this->level = 0;
        $this->newContents = array();
        $this->pageData = array();
        $this->processPages(json_decode($json, true));
    }

    /**
     * @param array[] $pages
     */
    private function processPages(array $pages)
    {
        $this->level++;
        foreach ($pages as $page) {
            $this->processPage($page);
        }
        $this->level--;
    }

    private function processPage(array $page)
    {
        $pattern = '/(copy_)?pagemanager-([0-9]*)/';
        $this->id = empty($page['attr']['id'])
            ? null
            : (int) preg_replace($pattern, '$2', $page['attr']['id']);
        $this->title = htmlspecialchars($page['attr']['title'], ENT_NOQUOTES, 'UTF-8');
        $this->pdattr = isset($page['attr']['data-pdattr'])
            ? $page['attr']['data-pdattr'] : null;
        $this->mayRename = $page['attr']['class'] == '';

        if (isset($this->contents[$this->id])) {
            $this->appendExistingPageContent();
        } else {
            $this->appendNewPageContent();
        }
        $this->appendPageData();

        $this->processPages($page['children']);
    }

    private function appendExistingPageContent()
    {
        $content = $this->contents[$this->id];
        if ($this->mayRename) {
            $content = $this->replaceHeading($content);
        }
        $this->newContents[] = $content;
    }

    /**
     * @param string $content
     * @return string
     */
    private function replaceHeading($content)
    {
        $pattern = "/<!--XH_ml[0-9]:.*?-->/";
        $replacement = "<!--XH_ml{$this->level}:"
            . addcslashes($this->title, '$\\') . '-->';
        return preg_replace($pattern, $replacement, $content, 1);
    }

    private function appendNewPageContent()
    {
        $this->newContents[] = "<!--XH_ml{$this->level}:{$this->title}-->";
    }

    private function appendPageData()
    {
        if (isset($this->id)) {
            $pageData = $this->pdRouter->find_page($this->id);
        } else {
            $pageData = $this->pdRouter->new_page();
            $pageData['last_edit'] = time();
        }
        if ($this->mayRename) {
            $pageData['url'] = uenc($this->title);
        }
        if ($this->pdattrName !== '') {
            $pageData[$this->pdattrName] = $this->pdattr;
        }
        $this->pageData[] = $pageData;
    }

    /**
     * @return string[]
     */
    public function getContents()
    {
        return $this->newContents;
    }

    /**
     * @return array[]
     */
    public function getPageData()
    {
        return $this->pageData;
    }
}
