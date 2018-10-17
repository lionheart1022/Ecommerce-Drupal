<?php

namespace Drupal\dcom_odoo_shipstation;

use Drupal\odoo_api\OdooApi\ClientInterface;

/**
 * Odoo transfer processor service.
 */
class TransferProcessor implements TransferProcessorInterface {
  const STOCK_PICKING_FIELDS = [
    'id',
    'move_lines',
    'state',
  ];

  /**
   * Drupal\odoo_api\OdooApi\ClientInterface definition.
   *
   * @var \Drupal\odoo_api\OdooApi\ClientInterface
   */
  protected $odoo;

  /**
   * Constructs a new TransferProcessor object.
   */
  public function __construct(ClientInterface $odoo_api_api_client) {
    $this->odoo = $odoo_api_api_client;
  }

  /**
   * {@inheritdoc}
   */
  public function fulfillOdooOrders(array $odoo_sale_ids) {
    $odoo_sale_ids = $this->excludeDeliveredOrders($odoo_sale_ids);

    $callbacks = [
      [$this, 'confirmDraftTransfers'],
      [$this, 'reserveWaitingTransfers'],
      [$this, 'processTransfers'],
      [$this, 'validateReadyTransfers'],
    ];

    $errors = [];
    foreach ($callbacks as $callback) {
      try {
        call_user_func_array($callback, [$odoo_sale_ids]);
      }
      catch (\Exception $e) {
        // Batch run failed, try running 1 one by one and see what failed.
        foreach ($odoo_sale_ids as $id) {
          try {
            call_user_func_array($callback, [[$id]]);
          }
          catch (\Exception $e) {
            $errors[$id] = $e->getMessage();
          }
        }
      }
    }

    return $errors;
  }

  /**
   * Count unprocessed moves for given transfers.
   *
   * @param int|array $stock_picking_id
   *   IDs of Odoo transfers.
   *
   * @return int
   *   Unprocessed moves count.
   */
  protected function unprocessedMoves($stock_picking_id) {
    if (!is_array($stock_picking_id)) {
      $stock_picking_id = [$stock_picking_id];
    }
    $filter = [
      ['picking_id', 'in', $stock_picking_id],
      ['state', 'not in', ['draft', 'cancel', 'done']],
    ];
    return $this->odoo->count('stock.move', $filter);
  }

  /**
   * Confirm Draft transfers.
   *
   * @param array $odoo_sale_ids
   *   IDs of Odoo orders.
   */
  protected function confirmDraftTransfers(array $odoo_sale_ids) {
    $filter = [
      ['sale_id', 'in', $odoo_sale_ids],
      ['state', '=', 'draft'],
    ];
    if ($draft_pickings = $this->odoo->search('stock.picking', $filter)) {
      $this->odoo->rawModelApiCall('stock.picking', 'action_confirm', $draft_pickings);
    }
  }

  /**
   * Reserve goods for waiting transfers.
   *
   * @param array $odoo_sale_ids
   *   IDs of Odoo orders.
   */
  protected function reserveWaitingTransfers(array $odoo_sale_ids) {
    $filter = [
      ['sale_id', 'in', $odoo_sale_ids],
      ['state', 'in', ['waiting', 'confirmed']],
    ];
    if ($waiting_pickings = $this->odoo->search('stock.picking', $filter)) {
      foreach ($waiting_pickings as $id) {
        // Reserve everything, but only if there are unprocessed moves.
        // Otherwise, the action_assign() method will raise an error.
        // @see https://github.com/odoo/odoo/blob/11.0/addons/stock/models/stock_picking.py#L587
        if ($this->unprocessedMoves($id)) {
          // The 'assign' action (which is essentially what 'Check
          // availability' button in UI does) method is a cheating function
          // which does most of the job on reserving for you. Basically, it
          // creates stock.move objects for products which are available.
          $this->odoo->rawModelApiCall('stock.picking', 'action_assign', [$id]);
        }
      }
    }
  }

  /**
   * Process pickings move lines.
   *
   * Move reserved items, create negative moves for products not in stock.
   *
   * @param array $odoo_sale_ids
   *   IDs of Odoo orders.
   */
  protected function processTransfers(array $odoo_sale_ids) {
    $filter = [
      ['sale_id', 'in', $odoo_sale_ids],
      ['state', 'in', ['waiting', 'confirmed', 'assigned']],
    ];

    if ($pickings = $this->odoo->search('stock.picking', $filter)) {
      $moves_filter = [['picking_id', 'in', $pickings]];
      $moves = $this->odoo->searchRead('stock.move', $moves_filter);
      foreach ($moves as $move) {
        $move_lines = $this->odoo->read('stock.move.line', $move['move_line_ids']);
        if (count($move_lines) == 0) {
          $move_line = $this->createMoveLine($move);
        }
        elseif (count($move_lines) == 1) {
          $move_line = reset($move_lines);
        }
        else {
          // @TODO: Try processing reserved quantities if there are multiple
          // @TODO: move lines.
          // @TODO: The same good may be located in different locations.
          throw new \Exception('Multiple move lines not supported.');
        }
        if ($move_line['qty_done'] < $move['product_uom_qty']) {
          $this->odoo->write('stock.move.line', [$move_line['id']], ['qty_done' => $move['product_uom_qty']]);
        }
      }
    }
  }

  /**
   * Create the move line to force create negative product amounts.
   *
   * @param array $stock_move
   *   Stock move object form Odoo.
   *
   * @return array
   *   Odoo move line object.
   */
  protected function createMoveLine(array $stock_move) {
    // I have no idea what these fields are. I've copied them from Odoo code.
    // @see https://github.com/odoo/odoo/blob/7ed86b53cdfbab9a7867de328604850138692caf/addons/stock/models/stock_move.py#L764
    $fields = [
      'move_id' => $stock_move['id'],
      'product_id' => $stock_move['product_id'][0],
      'product_uom_id' => $stock_move['product_uom'][0],
      'location_id' => $stock_move['location_id'][0],
      'location_dest_id' => $stock_move['location_dest_id'][0],
      'picking_id' => $stock_move['picking_id'][0],
    ];
    $id = $this->odoo->create('stock.move.line', $fields);
    $move_lines = $this->odoo->read('stock.move.line', [$id]);
    return reset($move_lines);
  }

  /**
   * Validate transfers.
   *
   * @param array $odoo_sale_ids
   *   IDs of Odoo orders.
   */
  protected function validateReadyTransfers(array $odoo_sale_ids) {
    $filter = [
      ['sale_id', 'in', $odoo_sale_ids],
      ['state', 'in', ['waiting', 'confirmed', 'assigned']],
    ];
    if ($pickings = $this->odoo->search('stock.picking', $filter)) {
      $this->odoo->rawModelApiCall('stock.picking', 'action_done', $pickings);
    }
  }

  /**
   * Check Odoo orders, exclude ones which are already shipped.
   *
   * @param array $odoo_sale_ids
   *   IDs of Odoo orders.
   *
   * @return array
   *   IDs of Odoo orders which aren't shipped yet.
   */
  protected function excludeDeliveredOrders(array $odoo_sale_ids) {
    $filter = [
      ['sale_id', 'in', $odoo_sale_ids],
      ['state', 'in', ['done']],
    ];
    $delivered_orders = [];
    foreach ($this->odoo->searchReadIterate('stock.picking', $filter, ['id', 'sale_id']) as $picking) {
      $delivered_orders[$picking['sale_id'][0]] = $picking['sale_id'][0];
    }
    $delivered_orders = array_values($delivered_orders);
    return array_values(array_diff($odoo_sale_ids, $delivered_orders));
  }

}
