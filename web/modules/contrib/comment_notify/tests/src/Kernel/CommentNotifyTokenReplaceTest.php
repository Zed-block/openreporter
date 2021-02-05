<?php

namespace Drupal\Tests\comment_notify\Kernel;

use Drupal\comment\Tests\CommentTestTrait;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\system\Kernel\Token\TokenReplaceKernelTestBase;
use Drupal\comment\Entity\Comment;

/**
 * Checks comment_notify token replacement.
 *
 * @group comment_notify
 */
class CommentNotifyTokenReplaceTest extends TokenReplaceKernelTestBase {

  use CommentTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['node', 'comment', 'comment_notify'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installConfig(['node', 'comment', 'comment_notify']);
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installSchema('comment_notify', ['comment_notify']);

    $node_type = NodeType::create(['type' => 'article', 'name' => 'Article']);
    $node_type->save();

    $this->addDefaultCommentField('node', 'article');
  }

  /**
   * Tests the tokens generated by comment notify.
   */
  public function testNodeTokenReplacement() {
    // Create a user, a node and a comment.
    $account = $this->createUser();
    /* @var $node \Drupal\node\NodeInterface */
    $node = Node::create([
      'type' => 'article',
      'tnid' => 0,
      'uid' => $account->id(),
      'title' => 'test',
    ]);
    $node->save();
    $comment = [
      'uid' => $account->id(),
      'entity_id' => $node->id(),
      'entity_type' => 'node',
      'field_name' => 'comment',
      'subject' => 'How much wood would a woodchuck chuck',
      'cid' => '',
      'pid' => '',
      'mail' => 'someone@example.com',
    ];
    $comment = Comment::create($comment);
    $comment->save();
    $notify_hash = $this->container->get('csrf_token')->get('127.0.0.1' . $comment->id());
    comment_notify_add_notification($comment->id(), TRUE, $notify_hash, 1);
    // Reload the comment to get its notify_hash.
    $comment = Comment::load($comment->id());

    // Generate and test tokens.
    $tests = [];
    $tests['[comment-subscribed:unsubscribe-url]'] = comment_notify_get_unsubscribe_url($comment);
    $tests['[comment-subscribed:author:uid]'] = $account->id();

    $base_bubbleable_metadata = BubbleableMetadata::createFromObject($comment);
    $metadata_tests = [];
    $metadata_tests['[comment-subscribed:unsubscribe-url]'] = $base_bubbleable_metadata;
    $bubbleable_metadata = clone $base_bubbleable_metadata;
    $metadata_tests['[comment-subscribed:author:uid]'] = $bubbleable_metadata->addCacheableDependency($account);

    // Test to make sure that we generated something for each token.
    $this->assertFalse(in_array(0, array_map('strlen', $tests)), 'No empty tokens generated.');

    foreach ($tests as $input => $expected) {
      $bubbleable_metadata = new BubbleableMetadata();
      $output = $this->tokenService->replace($input, ['comment-subscribed' => $comment], ['langcode' => $this->interfaceLanguage->getId()], $bubbleable_metadata);
      $this->assertEquals($expected, $output, new FormattableMarkup('Node token %token replaced.', ['%token' => $input]));
      $this->assertEquals($metadata_tests[$input], $bubbleable_metadata);
    }
  }

}