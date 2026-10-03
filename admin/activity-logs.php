<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/permissions.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/includes/admin-enhancements.php';

requireLogin();
if (function_exists('hasPermission') && !(hasPermission($conn, 'manage_users') || hasPermission($conn, 'manage_settings') || hasPermission($conn, 'manage_roles'))) {
    http_response_code(403);
    die('<h2>Access Denied</h2><p>You do not have permission to view activity logs.</p>');
}
adminTrackActivity($conn);
$page_title = 'Activity Logs';
adminEnsureActivityLogTable($conn);

$search = trim($_GET['search'] ?? '');
$action = trim($_GET['action'] ?? '');
$module = trim($_GET['module'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$where = ['1=1']; $params=[]; $types='';
if ($search !== '') { $where[]='(user_name LIKE ? OR description LIKE ? OR ip_address LIKE ?)'; $like="%{$search}%"; array_push($params,$like,$like,$like); $types.='sss'; }
if ($action !== '') { $where[]='action = ?'; $params[]=$action; $types.='s'; }
if ($module !== '') { $where[]='module = ?'; $params[]=$module; $types.='s'; }
$whereSql=implode(' AND ',$where);

$countSql="SELECT COUNT(*) FROM activity_logs WHERE {$whereSql}";
$st=$conn->prepare($countSql); if($types) $st->bind_param($types,...$params); $st->execute(); $total=(int)$st->get_result()->fetch_row()[0]; $st->close();

$sql="SELECT * FROM activity_logs WHERE {$whereSql} ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?";
$st=$conn->prepare($sql); $p2=$params; $p2[]=$perPage; $p2[]=$offset; $t2=$types.'ii'; $st->bind_param($t2,...$p2); $st->execute(); $logs=$st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();
$totalPages=max(1,(int)ceil($total/$perPage));

$today=adminScalar($conn,"SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at)=CURRENT_DATE");
$usersToday=adminScalar($conn,"SELECT COUNT(DISTINCT user_id) FROM activity_logs WHERE DATE(created_at)=CURRENT_DATE AND user_id IS NOT NULL");
$postActions=adminScalar($conn,"SELECT COUNT(*) FROM activity_logs WHERE method='POST'");
$modules=adminScalar($conn,"SELECT COUNT(DISTINCT module) FROM activity_logs");
$actions=$conn->query("SELECT DISTINCT action FROM activity_logs ORDER BY action")->fetch_all(MYSQLI_ASSOC);
$modulesList=$conn->query("SELECT DISTINCT module FROM activity_logs WHERE module IS NOT NULL AND module<>'' ORDER BY module")->fetch_all(MYSQLI_ASSOC);

include 'layout/header.php'; include 'layout/sidebar.php';
?>
<div class="content-header"><div><h2><i class="fas fa-clock-rotate-left"></i> Activity Logs</h2><p class="page-subtitle">Audit admin activity, changes, pages viewed and request origins.</p></div></div>
<div class="stats-grid">
 <div class="stat-card stat-card--blue"><div class="stat-icon"><i class="fas fa-list-check"></i></div><div class="stat-info"><div class="stat-value"><?=number_format($total)?></div><div class="stat-label">Total Activities</div></div></div>
 <div class="stat-card stat-card--green"><div class="stat-icon"><i class="fas fa-calendar-day"></i></div><div class="stat-info"><div class="stat-value"><?=number_format($today)?></div><div class="stat-label">Today</div></div></div>
 <div class="stat-card stat-card--amber"><div class="stat-icon"><i class="fas fa-user-clock"></i></div><div class="stat-info"><div class="stat-value"><?=number_format($usersToday)?></div><div class="stat-label">Active Users Today</div></div></div>
 <div class="stat-card stat-card--purple"><div class="stat-icon"><i class="fas fa-pen-to-square"></i></div><div class="stat-info"><div class="stat-value"><?=number_format($postActions)?></div><div class="stat-label">Change Requests</div></div></div>
</div>
<div class="card filter-card"><form method="get" class="admin-filter-grid">
 <div class="form-group"><label>Search</label><input type="text" name="search" value="<?=htmlspecialchars($search)?>" placeholder="User, description or IP"></div>
 <div class="form-group"><label>Action</label><select name="action"><option value="">All actions</option><?php foreach($actions as $a): ?><option value="<?=htmlspecialchars($a['action'])?>" <?=$action===$a['action']?'selected':''?>><?=htmlspecialchars(ucwords(str_replace(['-','_'],' ',$a['action'])))?></option><?php endforeach; ?></select></div>
 <div class="form-group"><label>Module</label><select name="module"><option value="">All modules</option><?php foreach($modulesList as $m): ?><option value="<?=htmlspecialchars($m['module'])?>" <?=$module===$m['module']?'selected':''?>><?=htmlspecialchars(ucwords(str_replace(['-','_'],' ',$m['module'])))?></option><?php endforeach; ?></select></div>
 <div class="filter-actions"><button class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button><a href="activity-logs.php" class="btn"><i class="fas fa-rotate-left"></i> Reset</a></div>
</form></div>
<div class="card"><div class="table-responsive"><table class="data-table"><thead><tr><th>Date/Time</th><th>User</th><th>Action</th><th>Module</th><th>Description</th><th>IP Address</th><th>Method</th></tr></thead><tbody>
<?php if(!$logs): ?><tr><td colspan="7" class="empty-state">No activity found.</td></tr><?php endif; ?>
<?php foreach($logs as $log): ?><tr><td><?=htmlspecialchars(date('d M Y, g:i A',strtotime($log['created_at'])))?></td><td><strong><?=htmlspecialchars($log['user_name'] ?: 'System / Guest')?></strong></td><td><span class="activity-action"><i class="fas fa-bolt"></i> <?=htmlspecialchars(ucwords(str_replace(['-','_'],' ',$log['action'])))?></span></td><td><?=htmlspecialchars(ucwords(str_replace(['-','_'],' ',$log['module']??'')))?></td><td><?=htmlspecialchars($log['description']??'')?></td><td><code><?=htmlspecialchars($log['ip_address']??'')?></code></td><td><span class="badge <?=($log['method']==='POST')?'badge-warning':'badge-success'?>"><?=htmlspecialchars($log['method']??'')?></span></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php if($totalPages>1): ?><div class="pagination"><?php for($i=max(1,$page-2);$i<=min($totalPages,$page+2);$i++): $q=$_GET; $q['page']=$i; ?><a class="page-link <?=$i===$page?'active':''?>" href="?<?=http_build_query($q)?>"><?=$i?></a><?php endfor; ?></div><?php endif; ?>
</div>
<?php include 'layout/footer.php'; ?>
