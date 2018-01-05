<?php

namespace Drupal\apigee_edge\Controller;

use Drupal\apigee_edge\Job;
use Drupal\apigee_edge\Job\DeveloperSync;
use Drupal\apigee_edge\JobExecutor;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for the user synchronization-related pages.
 */
class UserSyncController extends ControllerBase {

  /**
   * Job executor.
   *
   * @var \Drupal\apigee_edge\JobExecutor
   */
  protected $executor;

  /**
   * UserSyncController constructor.
   *
   * @param \Drupal\apigee_edge\JobExecutor $executor
   *   The job executor service.
   */
  public function __construct(JobExecutor $executor) {
    $this->executor = $executor;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Drupal\apigee_edge\JobExecutor $executor */
    $executor = $container->get('apigee_edge.job_executor');

    return new static($executor);
  }

  /**
   * Generates a job tag.
   *
   * @param string $type
   *   Tag type.
   *
   * @return string
   *   Job tag.
   */
  protected function generateTag(string $type) : string {
    return "user_sync_{$type}_" . user_password();
  }

  /**
   * Handler for 'apigee_edge.user_sync.schedule'.
   *
   * Runs a user sync in the background.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   HTTP response doing a redirect.
   */
  public function schedule(Request $request) {
    $destination = $request->query->get('destination');

    $job = new DeveloperSync();
    $job->setTag($this->generateTag('background'));
    apigee_edge_get_executor()->cast($job);

    \drupal_set_message($this->t('User synchronization is scheduled.'));

    return new RedirectResponse($destination);
  }

  /**
   * Handler for 'apigee_edge.user_sync.run'.
   *
   * Runs a user sync in the foreground as a batch.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   HTTP response doing a redirect.
   */
  public function run(Request $request) {
    $destination = $request->query->get('destination');
    $tag = $this->generateTag('batch');

    batch_set([
      'title' => $this->t('Synchronizing users'),
      'operations' => [
        [[static::class, 'batchGenerateJobs'], [$tag]],
        [[static::class, 'batchExecuteJobs'], [$tag]],
      ],
      'finished' => [static::class, 'batchFinished'],
    ]);

    return batch_process($destination);
  }

  /**
   * The first batch operation.
   *
   * This generates the user sync jobs for the second operation.
   *
   * @param string $tag
   *   Job tag.
   * @param array $context
   *   Batch context.
   */
  public static function batchGenerateJobs(string $tag, array &$context) {
    $job = new DeveloperSync();
    $job->setTag($tag);
    apigee_edge_get_executor()->call($job);

    $context['message'] = (string) $job;
    $context['finished'] = 1.0;
  }

  /**
   * The second batch operation.
   *
   * @param string $tag
   *   Job tag.
   * @param array $context
   *   Batch context.
   */
  public static function batchExecuteJobs(string $tag, array &$context) {
    if (!isset($context['sandbox'])) {
      $context['sandbox'] = [];
    }

    $executor = apigee_edge_get_executor();
    $job = $executor->select($tag);

    if ($job === NULL) {
      $context['finished'] = 1.0;
      return;
    }

    $executor->call($job);

    $context['message'] = (string) $job;
    $context['finished'] = $executor->countJobs($tag, [Job::FAILED, Job::FINISHED]) / $executor->countJobs($tag);
  }

  /**
   * Batch finish callback.
   */
  public static function batchFinished() {
    \drupal_set_message(t('Users are in sync with Edge.'));
  }

}