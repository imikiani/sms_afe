<?php

namespace Drupal\sms_afe\Plugin\SmsGateway;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\sms\Message\SmsDeliveryReport;
use Drupal\sms\Message\SmsMessageInterface;
use Drupal\sms\Message\SmsMessageReportStatus;
use Drupal\sms\Message\SmsMessageResult;
use Drupal\sms\Message\SmsMessageResultStatus;
use Drupal\sms\Plugin\SmsGatewayPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @SmsGateway(
 *   id = "afe",
 *   label = "afe.ir",
 *   outgoing_message_max_recipients = 89,
 * )
 */
class AFEGateway extends SmsGatewayPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Creates an instance of the plugin.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to pull out services used in the plugin.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   *
   * @return static
   *   Returns an instance of this plugin.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }


  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'username' => 'Enter your user name',
      'password' => 'Enter Your password',
      'sender_number' => '3000853853',
      'type' => 1,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['send'] = [
      '#type' => 'details',
      '#title' => $this->t('Outgoing Messages'),
      '#open' => TRUE,
    ];

    $form['send']['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User Name'),
      '#default_value' => $this->configuration['username'],
      '#description' => $this->t('Afe.ir service username'),
      '#required' => TRUE,

    ];
    $form['send']['password'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Password'),
      '#default_value' => $this->configuration['password'],
      '#description' => $this->t('Afe.ir service password.'),
      '#required' => TRUE,
    ];
    $form['send']['sender_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Sender Number'),
      '#default_value' => $this->configuration['sender_number'],
      '#description' => $this->t('Use 3000853853 for testing purposes or enter your main number.'),
      '#required' => TRUE,
    ];
    $form['send']['type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Type'),
      '#default_value' => $this->configuration['type'],
      '#description' => $this->t('Specifies that the message must be sent to the user inbox or as a flash message and so on. For more information see the documentation on afe.ir'),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['username'] = trim($form_state->getValue('username'));
    $this->configuration['password'] = trim($form_state->getValue('password'));
    $this->configuration['sender_number'] = trim($form_state->getValue('sender_number'));
    $this->configuration['type'] = trim($form_state->getValue('type'));
  }

  /**
   * {@inheritdoc}
   */
  public function send(SmsMessageInterface $sms_message) {
    $result = new SmsMessageResult();
    $parameters = [
      'Username' => $this->configuration['username'],
      'Password' => $this->configuration['password'],
      'Number' => $this->configuration['sender_number'],
      'Mobile' => $sms_message->getRecipients(),
      'Message' => $sms_message->getMessage(),
      'Type' => $this->configuration['type'],
    ];

    try {
      $client = new \SoapClient('http://www.afe.ir/WebService/V4/BoxService.asmx?wsdl');
      $gateway_response = $client->__SoapCall('SendMessage', [$parameters]);
      foreach ($sms_message->getRecipients() as $number) {
        $report = (new SmsDeliveryReport())
          ->setRecipient($number)
          ->setStatus(SmsMessageReportStatus::DELIVERED)
          ->setStatusMessage('DELIVERED')
          ->setTimeDelivered(REQUEST_TIME);
        $result->addReport($report);
      }
      \Drupal::logger('sms_afe')
        ->info('Gateway Response: ' . $gateway_response->SendMessageResult->string);
      return $result;
    } catch (\Exception $e) {
      \Drupal::logger('sms_afe')->error('Error: ' . $e->getMessage());
      return $result
        ->setError(SmsMessageResultStatus::ERROR)
        ->setErrorMessage($e->getMessage());
    }
  }

}