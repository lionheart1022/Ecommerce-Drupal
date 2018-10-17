<?php

namespace Drupal\dcom_odoo_shipstation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\dcom_odoo_shipstation\OrdersListInterface;
use Drupal\dcom_odoo_shipstation\ShipstationFormatterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Shipstation API endpoint controller.
 */
class ShipstationController extends ControllerBase {

  /**
   * Odoo Order query service.
   *
   * @var \Drupal\dcom_odoo_shipstation\OrdersListInterface
   */
  protected $orderQuery;

  /**
   * Shipstation XML formatter service.
   *
   * @var \Drupal\dcom_odoo_shipstation\ShipstationFormatterInterface
   */
  protected $formatter;

  /**
   * Controller constructor.
   *
   * @param \Drupal\dcom_odoo_shipstation\OrdersListInterface $order_query
   *   Odoo Order query service.
   * @param \Drupal\dcom_odoo_shipstation\ShipstationFormatterInterface $formatter
   *   Shipstation XML formatter service.
   */
  public function __construct(OrdersListInterface $order_query, ShipstationFormatterInterface $formatter) {
    $this->orderQuery = $order_query;
    $this->formatter = $formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dcom_odoo_shipstation.orders_list'),
      $container->get('dcom_odoo_shipstation.formatter')
    );
  }

  /**
   * Feed endpoint.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP request object.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   XML response.
   */
  public function endpoint(Request $request) {
    // @TODO: credentials.
    if ($request->query->get('SS-UserName') != 'diamondcbd'
      || $request->query->get('SS-Password') != 'UGL0R5jltGuMhoR') {
      // Unathorized.
      throw new HttpException(403);
    }

    // @TODO: configurable.
    $page_size = 100;

    $offset = ($request->query->get('page', 1) - 1) * $page_size;
    if ($start_date = $request->query->get('start_date')) {
      $start_date = DrupalDateTime::createFromFormat('m/d/Y H:i', $start_date)->getTimestamp();
    }
    if ($end_date = $request->query->get('end_date')) {
      $end_date = DrupalDateTime::createFromFormat('m/d/Y H:i', $end_date)->getTimestamp();
    }

    $orders = $this->orderQuery->getOrdersList($offset, $page_size, $start_date, $end_date);
    $count = $this->orderQuery->getOrdersCount($start_date, $end_date);
    $xml = $this->formatter->formatFeedResponse($orders, $count, $page_size);

    $response = new Response($xml);
    $response->headers->set('Content-Type', 'text/xml');

    return $response;
  }

}
