<?php

/**
 * @file
 * Contains \Drupal\anon_comment\Form\AnonCommentAdminOverview.
 */

namespace Drupal\anon_comment\Form;

use Drupal\comment\CommentInterface;
use Drupal\comment\CommentStorageInterface;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the comments overview administration form.
 */
class AnonCommentAdminOverview extends FormBase {

  /**
   * The entity storage.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The comment storage.
   *
   * @var \Drupal\comment\CommentStorageInterface
   */
  protected $commentStorage;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Creates a CommentAdminOverview form.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager service.
   * @param \Drupal\comment\CommentStorageInterface $comment_storage
   *   The comment storage.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(EntityManagerInterface $entity_manager, CommentStorageInterface $comment_storage, DateFormatterInterface $date_formatter, ModuleHandlerInterface $module_handler) {
    $this->entityManager = $entity_manager;
    $this->commentStorage = $comment_storage;
    $this->dateFormatter = $date_formatter;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager'),
      $container->get('entity.manager')->getStorage('comment'),
      $container->get('date.formatter'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'anon_comment_admin_overview';
  }

  /**
   * Form constructor for the comment overview administration form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $type
   *   The type of the overview form ('approval' or 'new').
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $type = 'new') {

    // Build an 'Update options' form.
    $form['options'] = array(
      '#type' => 'details',
      '#title' => $this->t('Update options'),
      '#open' => TRUE,
      '#attributes' => array('class' => array('container-inline')),
    );

    $options = array(
      'unpublish' => t('Unpublish the selected comments'),
      'modify' => t('De-anonymize the selected comments'),
      'delete' => t('Delete the selected comments'),
    );

    $form['options']['operation'] = array(
      '#type' => 'select',
      '#title' => $this->t('Action'),
      '#title_display' => 'invisible',
      '#options' => $options,
      '#default_value' => 'publish',
    );
    $form['options']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Update'),
    );

    // Load the comments that need to be displayed.
    $header = array(
      'subject' => array(
        'data' => $this->t('Subject'),
        'specifier' => 'subject',
      ),
      'actual_author' => array(
        'data' => $this->t('Actual Author'),
        'specifier' => 'name',
        'class' => array(RESPONSIVE_PRIORITY_MEDIUM),
      ),
      'posted_in' => array(
        'data' => $this->t('Posted in'),
        'class' => array(RESPONSIVE_PRIORITY_LOW),
      ),
      'changed' => array(
        'data' => $this->t('Updated'),
        'specifier' => 'changed',
        'sort' => 'desc',
        'class' => array(RESPONSIVE_PRIORITY_LOW),
      ),
      'operations' => $this->t('Operations'),
    );

    $db = \Drupal::database();
    $query = $db->select('comment_field_data', 'c');
    $query->join('node_field_data', 'n', 'n.nid = c.entity_id');
    $query->addField('n', 'title', 'node_title');
    $query->join('anon_comment_authors', 'a', 'c.cid = a.cid');
    $query->join('users', 'u', 'u.uid = a.uid');
    $query->addField('u', 'uid', 'user_id');
    $query->addTag('node_access');
    $query->fields('c', array('cid', 'subject', 'name', 'changed'));

    $result = $query->execute();

    $cids = array();

    // We collect a sorted list of node_titles during the query to attach to the
    // comments later.
    foreach ($result as $row) {
      $cids[] = $row->cid;
      $node_titles[] = $row->node_title;
      $user_ids[] = $row->user_id;
    }

    // @var $comments \Drupal\comment\CommentInterface[]
    $comments = $this->commentStorage->loadMultiple($cids);

    // Build a table listing the appropriate comments.
    $options = array();
    $destination = $this->getDestinationArray();

    $commented_entity_ids = array();
    $commented_entities = array();

    foreach ($comments as $comment) {
      $commented_entity_ids[$comment->getCommentedEntityTypeId()][] = $comment->getCommentedEntityId();
    }

    foreach ($commented_entity_ids as $entity_type => $ids) {
      $commented_entities[$entity_type] = $this->entityManager->getStorage($entity_type)->loadMultiple($ids);
    }

    foreach ($comments as $comment) {
      // @var $commented_entity \Drupal\Core\Entity\EntityInterface.
      $commented_entity = $commented_entities[$comment->getCommentedEntityTypeId()][$comment->getCommentedEntityId()];
      $comment_permalink = $comment->permalink();

      $author = User::load(array_shift($user_ids));

      if ($comment->hasField('comment_body') && ($body = $comment->get('comment_body')->value)) {
        $attributes = $comment_permalink->getOption('attributes') ?: array();
        $attributes += array('title' => Unicode::truncate($body, 128));
        $comment_permalink->setOption('attributes', $attributes);
      }
      $options[$comment->id()] = array(
        'title' => array('data' => array('#title' => $comment->getSubject() ?: $comment->id())),
        'subject' => array(
          'data' => array(
            '#type' => 'link',
            '#title' => $comment->getSubject(),
            '#url' => $comment_permalink,
          ),
        ),
        'actual_author' => array(
          'data' => array(
            '#theme' => 'username',
            '#account' => $author,
          ),
        ),
        'posted_in' => array(
          'data' => array(
            '#type' => 'link',
            '#title' => $commented_entity->label(),
            '#access' => $commented_entity->access('view'),
            '#url' => $commented_entity->urlInfo(),
          ),
        ),
        'changed' => $this->dateFormatter->format($comment->getChangedTimeAcrossTranslations(), 'short'),
      );
      $comment_uri_options = $comment->urlInfo()->getOptions() + ['query' => $destination];
      $links = array();
      $links['edit'] = array(
        'title' => $this->t('Edit'),
        'url' => $comment->urlInfo('edit-form', $comment_uri_options),
      );
      if ($this->moduleHandler->moduleExists('content_translation') && $this->moduleHandler->invoke('content_translation', 'translate_access', array($comment))->isAllowed()) {
        $links['translate'] = array(
          'title' => $this->t('Translate'),
          'url' => $comment->urlInfo('drupal:content-translation-overview', $comment_uri_options),
        );
      }
      $options[$comment->id()]['operations']['data'] = array(
        '#type' => 'operations',
        '#links' => $links,
      );
    }

    $form['comments'] = array(
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $options,
      '#empty' => $this->t('No comments available.'),
    );

    $form['pager'] = array('#type' => 'pager');

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $form_state->setValue('comments', array_diff($form_state->getValue('comments'), array(0)));
    // We can't execute any 'Update options' if no comments were selected.
    if (count($form_state->getValue('comments')) == 0) {
      $form_state->setErrorByName('', $this->t('Select one or more comments to perform the update on.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (!empty( $form_state->getValue('operation'))) {
      $operation = $form_state->getValue('operation');
      $cids      = $form_state->getValue('comments');

      //perform the operation
      switch ($operation) {
        case "unpublish":
          foreach ($cids as $cid) {
            $comment = $this->commentStorage->load($cid);
            $comment->setPublished(FALSE);
            $comment->save();
          }
          break;
        case "publish":
          foreach ($cids as $cid) {
            $comment = $this->commentStorage->load($cid);
            $comment->setPublished(TRUE);
            $comment->save();
          }
          break;
        case "modify":
          $db = \Drupal::database();
          $query = $db->select('comment_field_data', 'c');
          $query->join('node_field_data', 'n', 'n.nid = c.entity_id');
          $query->addField('n', 'title', 'node_title');
          $query->join('anon_comment_authors', 'a', 'c.cid = a.cid');
          $query->join('users', 'u', 'u.uid = a.uid');
          $query->addField('u', 'uid', 'user_id');
          $query->addTag('node_access');
          $query->fields('c', array('cid', 'subject', 'name', 'changed'));

          $result = $query->execute();

          $user_ids = array();
          foreach ($result as $row) {
            $user_ids[$row->cid] = $row->user_id;
          }

          foreach ($cids as $cid) {
            $author = '';
            foreach ($user_ids as $key => $user_id) {
              if($key == $cid) {
                $author = User::load($user_id);
              }
            }
            $comment = $this->commentStorage->load($cid);
            $comment->setOwner($author);
            $comment->save();

            $db->delete('anon_comment_authors')->condition('cid', $comment->get('cid')->value)->execute();
          }
          break;
      }
      drupal_set_message($this->t('The update has been performed.'));
    }
  }
}
