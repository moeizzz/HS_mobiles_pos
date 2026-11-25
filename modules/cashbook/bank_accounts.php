<?php
/**
 * Bank Accounts - add / edit / activate / delete the shop's bank accounts and see each balance.
 * Entries (deposits / withdrawals) live in bank_entries.php.
 */
session_start();
$page_title = 'Cash Book - Bank Accounts';
$base_url = '../../';
require_once '../../includes/functions.php';
require_once __DIR__ . '/_common.php';

const BANK_TYPES = ['current' => 'Current', 'savings' => 'Savings', 'loan' => 'Loan / Credit line'];

/** Validate the add/edit form. Returns [error, cleanData]. */
function bank_validate(PDO $pdo, array $in, int $editId = 0) {
    $d = [
        'account_name'    => trim((string)($in['account_name'] ?? '')),
        'bank_name'       => trim((string)($in['bank_name'] ?? '')),
        'account_no'      => trim((string)($in['account_no'] ?? '')),
        'account_type'    => (string)($in['account_type'] ?? 'current'),
        'branch_code'     => trim((string)($in['branch_code'] ?? '')),
        'opening_balance' => round((float)($in['opening_balance'] ?? 0), 2),
    ];
    if ($d['account_name'] === '') return ['Account title is required.', $d];
    if ($d['bank_name'] === '')    return ['Bank name is required.', $d];
    if ($d['account_no'] === '')   return ['Account number is required.', $d];
    if (mb_strlen($d['account_name']) > 100 || mb_strlen($d['bank_name']) > 100 || mb_strlen($d['account_no']) > 50 || mb_strlen($d['branch_code']) > 50) return ['A field is too long.', $d];
    if (!isset(BANK_TYPES[$d['account_type']])) return ['Select a valid account type.', $d];
    if ($d['opening_balance'] < 0) return ['Opening balance cannot be negative.', $d];
    if ($d['opening_balance'] > 1000000000) return ['Opening balance looks too large.', $d];
    $st = $pdo->prepare("SELECT id FROM bank_accounts WHERE account_no = ? AND bank_name = ? AND id <> ?");
    $st->execute([$d['account_no'], $d['bank_name'], $editId]);
    if ($st->fetchColumn()) return ['This account number already exists for ' . $d['bank_name'] . '.', $d];
    return ['', $d];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'add') {
        [$err, $d] = bank_validate($pdo, $_POST);
        if ($err) redirect('bank_accounts.php', $err, 'error');
        insert('bank_accounts', $d + ['current_balance' => $d['opening_balance'], 'status' => 1, 'created_at' => date('Y-m-d'), 'updated_at' => date('Y-m-d')]);
        redirect('bank_accounts.php', 'Bank account added.');
    }

    $acc = $id ? getById('bank_accounts', $id) : null;
    if (!$acc) redirect('bank_accounts.php', 'Bank account not found.', 'error');

    if ($action === 'edit') {
        [$err, $d] = bank_validate($pdo, $_POST, $id);
        if ($err) redirect('bank_accounts.php?edit=' . $id, $err, 'error');
        // keep the running balance consistent when the opening balance is corrected
        $delta = $d['opening_balance'] - (float)$acc['opening_balance'];
        $d['current_balance'] = round((float)$acc['current_balance'] + $delta, 2);
        $d['updated_at'] = date('Y-m-d');
        update('bank_accounts', $d, $id);
        redirect('bank_accounts.php', 'Bank account updated.');
    }

    if ($action === 'toggle') {
        if ((int)$acc['status'] === 1) {
            $active = (int)$pdo->query("SELECT COUNT(*) FROM bank_accounts WHERE status = 1")->fetchColumn();
            if ($active <= 1) redirect('bank_accounts.php', 'At least one bank account must stay active (bank payments need an account).', 'error');
        }
        update('bank_accounts', ['status' => (int)$acc['status'] === 1 ? 0 : 1, 'updated_at' => date('Y-m-d')], $id);
        redirect('bank_accounts.php', 'Account ' . ((int)$acc['status'] === 1 ? 'deactivated.' : 'activated.'));
    }

    if ($action === 'delete') {
        $n1 = $pdo->prepare("SELECT COUNT(*) FROM bank_transactions WHERE bank_account_id = ?"); $n1->execute([$id]);
        $n2 = $pdo->prepare("SELECT COUNT(*) FROM supplier_payments WHERE bank_account_id = ?"); $n2->execute([$id]);
        if ((int)$n1->fetchColumn() > 0 || (int)$n2->fetchColumn() > 0) {
            redirect('bank_accounts.php', 'Cannot delete: this account already has entries. Deactivate it instead.', 'error');
        }
        $active = (int)$pdo->query("SELECT COUNT(*) FROM bank_accounts WHERE status = 1 AND id <> " . $id)->fetchColumn();
        if ($active < 1) redirect('bank_accounts.php', 'Cannot delete the only active account.', 'error');
        delete('bank_accounts', $id);
        redirect('bank_accounts.php', 'Bank account deleted.');
    }
    redirect('bank_accounts.php', 'Unknown action.', 'error');
}

$edit = null;
if (isset($_GET['edit'])) $edit = getById('bank_accounts', (int)$_GET['edit']);

$accounts = $pdo->query("SELECT a.*,
      COALESCE((SELECT SUM(CASE WHEN t.transaction_type IN ('deposit','transfer_in') THEN t.amount ELSE -t.amount END) FROM bank_transactions t WHERE t.bank_account_id = a.id), 0) AS movement,
      (SELECT COUNT(*) FROM bank_transactions t2 WHERE t2.bank_account_id = a.id) AS entries,
      COALESCE((SELECT SUM(t3.amount) FROM bank_transactions t3 WHERE t3.bank_account_id = a.id AND t3.transaction_type IN ('deposit','transfer_in')), 0) AS dep,
      COALESCE((SELECT SUM(t4.amount) FROM bank_transactions t4 WHERE t4.bank_account_id = a.id AND t4.transaction_type IN ('withdrawal','transfer_out')), 0) AS wd
    FROM bank_accounts a ORDER BY a.status DESC, a.bank_name, a.account_name")->fetchAll();
$total = 0.0; foreach ($accounts as $a) if ((int)$a['status'] === 1) $total += (float)$a['current_balance'];

require_once '../../includes/header.php';
$f = $edit ?: ['id' => 0, 'account_name' => '', 'bank_name' => '', 'account_no' => '', 'account_type' => 'current', 'branch_code' => '', 'opening_balance' => '0'];
?>

<div class="row">
  <div class="col-lg-4 mb-4">
    <div class="card shadow">
      <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-<?= $edit ? 'edit' : 'plus-circle' ?>"></i> <?= $edit ? 'Edit Bank Account' : 'Add Bank Account' ?></h6></div>
      <div class="card-body">
        <form method="post" autocomplete="off">
          <input type="hidden" name="action" value="<?= $edit ? 'edit' : 'add' ?>">
          <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
          <div class="form-group"><label>Account Title <span class="text-danger">*</span></label>
            <input type="text" name="account_name" class="form-control" maxlength="100" value="<?= cb_h($f['account_name']) ?>" placeholder="Account title" required></div>
          <div class="form-group"><label>Bank Name <span class="text-danger">*</span></label>
            <input type="text" name="bank_name" class="form-control" maxlength="100" value="<?= cb_h($f['bank_name']) ?>" placeholder="e.g. HBL, Meezan, JazzCash" required></div>
          <div class="form-group"><label>Account No. / IBAN <span class="text-danger">*</span></label>
            <input type="text" name="account_no" class="form-control" maxlength="50" value="<?= cb_h($f['account_no']) ?>" required></div>
          <div class="form-row">
            <div class="form-group col-6"><label>Type</label>
              <select name="account_type" class="form-control">
                <?php foreach (BANK_TYPES as $k => $lbl): ?><option value="<?= $k ?>" <?= $f['account_type'] === $k ? 'selected' : '' ?>><?= $lbl ?></option><?php endforeach; ?>
              </select></div>
            <div class="form-group col-6"><label>Branch Code</label>
              <input type="text" name="branch_code" class="form-control" maxlength="50" value="<?= cb_h($f['branch_code']) ?>"></div>
          </div>
          <div class="form-group"><label>Opening Balance (PKR)</label>
            <input type="number" name="opening_balance" class="form-control" step="0.01" min="0" value="<?= cb_h($f['opening_balance']) ?>">
            <?php if ($edit): ?><small class="text-muted">Changing this also moves the current balance by the same amount.</small><?php endif; ?></div>
          <button class="btn btn-primary btn-block"><i class="fas fa-save"></i> <?= $edit ? 'Update Account' : 'Add Account' ?></button>
          <?php if ($edit): ?><a href="bank_accounts.php" class="btn btn-outline-secondary btn-block">Cancel</a><?php endif; ?>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-8 mb-4">
    <div class="card shadow">
      <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-university"></i> Bank Accounts (<?= count($accounts) ?>)</h6>
        <span class="small text-muted">Total balance (active): <b class="money <?= $total < 0 ? 'text-danger' : '' ?>"><?= cb_money($total) ?></b></span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered table-hover table-sm mb-0">
            <thead class="thead-light"><tr><th>Account</th><th>Bank / No.</th><th class="text-right">Opening</th><th class="text-right">Deposits</th><th class="text-right">Withdrawals</th><th class="text-right">Balance</th><th class="text-center">Status</th><th class="text-center no-print">Actions</th></tr></thead>
            <tbody>
            <?php if (!$accounts): ?><tr><td colspan="8" class="text-center text-muted py-4">No bank account yet. Add one on the left.</td></tr><?php endif; ?>
            <?php foreach ($accounts as $a):
              $calc = round((float)$a['opening_balance'] + (float)$a['movement'], 2);
              $mismatch = abs($calc - (float)$a['current_balance']) > 0.009; ?>
              <tr class="<?= (int)$a['status'] ? '' : 'text-muted' ?>">
                <td class="font-weight-bold"><?= cb_h($a['account_name']) ?><div class="small text-muted"><?= cb_h(BANK_TYPES[$a['account_type']] ?? $a['account_type']) ?></div></td>
                <td><?= cb_h($a['bank_name']) ?><div class="small text-mono text-muted"><?= cb_h($a['account_no']) ?><?= $a['branch_code'] ? ' / ' . cb_h($a['branch_code']) : '' ?></div></td>
                <td class="text-right money"><?= cb_money($a['opening_balance']) ?></td>
                <td class="text-right money text-success"><?= cb_money($a['dep']) ?></td>
                <td class="text-right money text-danger"><?= cb_money($a['wd']) ?></td>
                <td class="text-right money font-weight-bold <?= $a['current_balance'] < 0 ? 'text-danger' : '' ?>"><?= cb_money($a['current_balance']) ?>
                  <?php if ($mismatch): ?><i class="fas fa-exclamation-triangle text-warning" title="Balance does not match opening + entries (<?= cb_money($calc) ?>)"></i><?php endif; ?></td>
                <td class="text-center"><span class="badge badge-<?= (int)$a['status'] ? 'success' : 'secondary' ?>"><?= (int)$a['status'] ? 'Active' : 'Inactive' ?></span></td>
                <td class="text-center no-print text-nowrap">
                  <a href="bank_entries.php?account=<?= (int)$a['id'] ?>" class="btn btn-sm btn-outline-primary" title="Entries"><i class="fas fa-list"></i></a>
                  <a href="bank_accounts.php?edit=<?= (int)$a['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="fas fa-edit"></i></a>
                  <form method="post" class="d-inline"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                    <button class="btn btn-sm btn-outline-warning" title="<?= (int)$a['status'] ? 'Deactivate' : 'Activate' ?>"><i class="fas fa-power-off"></i></button></form>
                  <?php if ((int)$a['entries'] === 0): ?>
                  <form method="post" class="d-inline" onsubmit="return confirm('Delete this bank account?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button></form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
