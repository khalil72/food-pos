<?php
declare(strict_types=1);

final class AuditController extends Controller
{
    public function index(): void
    {
        [$from, $to, $preset] = date_range('7d');
        $where = ['a.created_at BETWEEN ? AND ?'];
        $params = [$from . ' 00:00:00', $to . ' 23:59:59'];
        if ($uid = input_int('user')) {
            $where[] = 'a.user_id = ?';
            $params[] = $uid;
        }
        if (($action = (string)input('action', '')) !== '') {
            $where[] = 'a.action = ?';
            $params[] = $action;
        }
        if (($q = (string)input('q', '')) !== '') {
            $where[] = '(a.details LIKE ? OR a.ip LIKE ?)';
            array_push($params, "%$q%", "%$q%");
        }
        $w = implode(' AND ', $where);
        $p = paginate((int)$this->db->value("SELECT COUNT(*) FROM audit_logs a WHERE $w", $params), 40);
        $logs = $this->db->fetchAll(
            "SELECT a.*, u.name user_name FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE $w ORDER BY a.id DESC LIMIT {$p['per_page']} OFFSET {$p['offset']}",
            $params
        );
        $this->view('audit/index', [
            'title' => 'Audit Log',
            'subtitle' => 'Every sign-in, sale, refund and change, with who did it',
            'logs' => $logs, 'p' => $p, 'from' => $from, 'to' => $to, 'preset' => $preset,
            'users' => $this->db->fetchAll('SELECT id, name FROM users ORDER BY name'),
            'actions' => array_column($this->db->fetchAll('SELECT DISTINCT action FROM audit_logs ORDER BY action'), 'action'),
        ]);
    }
}
