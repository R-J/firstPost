<?php

$PluginInfo['firstPost'] = array(
    'Name' => 'First Post',
    'Description' => 'Add a css class to the first discussion/first comment of a user.',
    'Version' => '0.1',
    'RequiredApplications' => array('Vanilla' => '>= 2.2'),
    'RequiredPlugins' => false,
    'RequiredTheme' => false,
    'MobileFriendly' => true,
    'HasLocale' => false,
    'Author' => 'Robin Jurinka',
    'AuthorUrl' => 'http://vanillaforums.org/profile/44046/R_J',
    'License' => 'MIT'
);

class FirstPostPlugin extends Gdn_Plugin {

    /**
     * Scan discussions and comments and update the first post info.
     *
     * This way of doing it is error prone. PHP can face a time out and some
     * of the discussions/comments will never be "indexed".
     *
     * @return void.
     */
    public function structure() {
        $discussionSql = Gdn::sql()
            ->select('d.InsertUserID, d.DateInserted, d.Attributes')
            ->select("'Discussion'", '', 'PostType')
            ->select('d.DiscussionID', 'min', 'PostID')
            ->from('Discussion d')
            ->groupBy('d.InsertUserID')
            ->getSelect();
        $commentSql = Gdn::sql()
            ->select('c.InsertUserID, c.DateInserted, c.Attributes')
            ->select("'Comment'", '', 'PostType')
            ->select('c.CommentID', 'min', 'PostID')
            ->from('Comment c')
            ->groupBy('c.InsertUserID')
            ->orderBy('InsertUserID')
            ->orderBy('DateInserted', 'asc')
            ->getSelect();

        $sql = $discussionSql.' UNION '.$commentSql;
        $result = Gdn::sql()->query($sql)->resultArray();

        $CommentModel = new commentModel();
        $DiscussionModel = new discussionModel();

        $lastUserID = 0;
        foreach ($result as $post) {
            // Is this the very firs post or "only" the second post, but the
            // first discussion/comment.
            if ($lastUserID != $post['InsertUserID']) {
                $FirstPost = 'FirstPost';
            } else {
                $FirstPost = 'First'.$post['PostType'];
            }

            // Ensure that attributes are not null.
            if ($post['Attributes'] == null) {
                $newAttributes = array('FirstPost' => $FirstPost);
            } else {
                $newAttributes = array_merge(
                    $post['Attributes'],
                    array('FirstPost' => $FirstPost)
                );
            }

            // Save the new attributes field.
            ${$post['PostType'].'Model'}->setField(
                $post['PostID'],
                'Attributes',
                $newAttributes
            );
            $lastUserID = $post['InsertUserID'];
        }
    }

    /**
     * Calls function for setting flag on each discussion save.
     *
     * @param discussionModel $sender The sending object.
     * @param mixed $args Array of DiscussionID and FormPostValues.
     * @return void.
     */
    public function discussionModel_beforeSaveDiscussion_handler($sender, $args) {
        $this->setAttribute($args, 'Discussion');
    }

    /**
     * Calls function for setting flag on each comment save.
     *
     * @param commentModel $sender The sending object.
     * @param mixed $args Array of CommentID and FormPostValues.
     * @return void.
     */
    public function commentModel_beforeSaveComment_handler($sender, $args) {
        $this->setAttribute($args, 'Comment');
    }

    /**
     * Sets flag for first content of a user.
     *
     * This function adds an attribute to new comments/discussions if they are
     * the first of that post type the user creates. FirstPost attribute is set
     * to "FirstPost" for the very first post a user makes and to
     * "FirstDiscussion"/"FirstComment" for the first discussion/comment if
     * the user has made already made a comment/discussion.
     *
     * @param mixed $args Array with FormPostValues of the beforeSave event.
     * @param string $postType The post type. Either Comment or Discussion.
     */
    private function setAttribute($args, $postType = 'Discussion') {
        // If editing existing content, nothing must be done.
        if ($args[$postType.'ID'] > 0) {
            return false;
        }

        // Stop if this is not the first post of given type.
        $insertUser = Gdn::userModel()->getID(
            $args['FormPostValues']['InsertUserID']
        );
        if ($insertUser->{'Count'.$postType.'s'} >= 1) {
            return false;
        }

        // Check if this is not only the first post of type X but also the very
        // first post independent on post type.
        if ($insertUser->CountDiscussions + $insertUser->CountComments == 0) {
            $postType = 'Post';
        }

        // Add "first postType" flag
        if (isset($args['FormPostValues']['Attributes'])) {
            $args['FormPostValues']['Attributes'] = array();
        }
        $args['FormPostValues']['Attributes']['FirstPost'] = 'First'.$postType;

        return true;
    }

    /**
     * Update discussion class in discussion lists.
     *
     * @param baseController $sender The calling controller.
     * @param mixed $args EventArguments containing the Discussion object.
     * @return void.
     */
    public function base_beforeDiscussionName_handler($sender, $args) {
        $sender->addCssFile('firstpost.css', 'plugins/firstPost');
        $attributes = val('Attributes', $args['Discussion']);
        $args['CssClass'] .= ' '.val('FirstPost', $attributes);
    }

    /**
     * Update discussion class.
     *
     * @param discussionController $sender The calling controller.
     * @param mixed $args EventArguments containing the Discussion object.
     * @return void.
     */
    public function discussionController_beforeDiscussionDisplay_handler($sender, $args) {
        $sender->addCssFile('firstpost.css', 'plugins/firstPost');
        $attributes = val('Attributes', $args['Discussion']);
        $args['CssClass'] .= ' '.val('FirstPost', $attributes);
    }

    /**
     * Update comment class.
     *
     * @param discussionController $sender The calling controller.
     * @param mixed $args EventArguments containing the Comment object.
     * @return void.
     */
    public function discussionController_beforeCommentDisplay_handler($sender, $args) {
        $sender->addCssFile('firstpost.css', 'plugins/firstPost');
        $attributes = val('Attributes', $args['Comment']);
        $args['CssClass'] .= ' '.val('FirstPost', $attributes);
    }
}
