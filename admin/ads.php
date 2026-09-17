<?php require_once dirname(__DIR__).'/core/bootstrap.php';require_admin();$title='مدیریت تبلیغات';

function save_ad_image(array $f): string {
    $root = dirname(__DIR__);
    $error = $f['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error !== UPLOAD_ERR_OK) {
        // Distinguish "no file chosen" from "a file was chosen but the server
        // rejected it" — these used to share the same generic "تصویر لازم است"
        // message, which was misleading whenever the real cause was a size
        // limit (PHP's own upload_max_filesize/post_max_size, hit before our
        // 5MB app-level check even runs) rather than a missing file.
        $message = match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'حجم تصویر از حد مجاز سرور بیشتر است. تصویر کوچک‌تری انتخاب کنید.',
            UPLOAD_ERR_PARTIAL => 'آپلود تصویر ناقص انجام شد. دوباره تلاش کنید.',
            UPLOAD_ERR_NO_FILE => 'تصویر لازم است.',
            default => 'خطا در آپلود تصویر (کد ' . $error . ').',
        };
        throw new Exception($message);
    }
    if (($f['size'] ?? 0) > 5 * 1024 * 1024) throw new Exception('حداکثر حجم ۵ مگابایت است.');
    $fi = new finfo(FILEINFO_MIME_TYPE);
    $mime = $fi->file($f['tmp_name']);
    $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($map[$mime])) throw new Exception('فرمت تصویر مجاز نیست.');
    $dir = $root . '/uploads/ads';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = bin2hex(random_bytes(16)) . '.' . $map[$mime];
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) throw new Exception('ذخیره تصویر ناموفق بود.');
    return 'uploads/ads/' . $name;
}

/** Converts a <input type=datetime-local> value (or empty) to a MySQL DATETIME or null. */
function parse_local_datetime(string $value): ?string {
    $value = trim($value);
    if ($value === '') return null;
    $ts = strtotime($value);
    if ($ts === false) throw new Exception('تاریخ نامعتبر است.');
    return gmdate('Y-m-d H:i:s', $ts);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $a = $_POST['action'] ?? '';

    if ($a === 'save') {
        try {
            $id = (int)($_POST['id'] ?? 0);
            $titlev = trim($_POST['title'] ?? '');
            $target = trim($_POST['target_url'] ?? '');
            if ($target !== '' && !validate_http_url($target)) throw new Exception('لینک مقصد معتبر نیست.');
            $placement = array_key_exists($_POST['placement'] ?? '', ad_placements()) ? $_POST['placement'] : 'pre_connect';
            $seconds = max(0, min(60, (int)($_POST['display_seconds'] ?? 4)));
            $priority = (int)($_POST['priority'] ?? 0);
            $active = isset($_POST['active']) ? 1 : 0;
            $startsAt = parse_local_datetime($_POST['starts_at'] ?? '');
            $endsAt = parse_local_datetime($_POST['ends_at'] ?? '');
            if ($startsAt && $endsAt && $startsAt > $endsAt) throw new Exception('تاریخ پایان باید بعد از تاریخ شروع باشد.');

            $image = '';
            if ($id) {
                $st = $pdo->prepare('SELECT image_path FROM ads WHERE id=?');
                $st->execute([$id]);
                $image = (string)($st->fetchColumn() ?: '');
            }
            if (!empty($_FILES['image']['name'])) $image = save_ad_image($_FILES['image']);
            if (!$titlev || !$image) throw new Exception('عنوان و تصویر الزامی است.');

            if ($id) {
                $pdo->prepare('UPDATE ads SET title=?,image_path=?,target_url=?,placement=?,display_seconds=?,priority=?,active=?,starts_at=?,ends_at=?,updated_at=NOW() WHERE id=?')
                    ->execute([$titlev, $image, $target ?: null, $placement, $seconds, $priority, $active, $startsAt, $endsAt, $id]);
            } else {
                $pdo->prepare("INSERT INTO ads(title,image_path,target_url,placement,plan,display_seconds,priority,active,starts_at,ends_at,created_at,updated_at) VALUES(?,?,?,?,'free',?,?,?,?,?,NOW(),NOW())")
                    ->execute([$titlev, $image, $target ?: null, $placement, $seconds, $priority, $active, $startsAt, $endsAt]);
            }
            flash('success', 'تبلیغ ذخیره شد.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/admin/ads.php');
    }

    if ($a === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE ads SET active = 1 - active, updated_at=NOW() WHERE id=?')->execute([$id]);
        redirect('/admin/ads.php');
    }

    if ($a === 'delete') {
        $id = (int)$_POST['id'];
        $st = $pdo->prepare('SELECT image_path FROM ads WHERE id=?');
        $st->execute([$id]);
        $img = $st->fetchColumn();
        $pdo->prepare('DELETE FROM ads WHERE id=?')->execute([$id]);
        if ($img) { $p = dirname(__DIR__) . '/' . $img; if (is_file($p)) @unlink($p); }
        flash('success', 'حذف شد.');
        redirect('/admin/ads.php');
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM ads WHERE id=?');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch();
}

$list = $pdo->query('SELECT * FROM ads ORDER BY placement ASC, priority DESC, id DESC')->fetchAll();
$totalActive = 0; $totalImpr = 0; $totalClicks = 0;
foreach ($list as $row) { if ($row['active']) $totalActive++; $totalImpr += (int)$row['impressions']; $totalClicks += (int)$row['clicks']; }

require __DIR__ . '/_header.php';
?>
<div class="grid">
  <div class="card"><span class="muted">تبلیغات فعال</span><div class="metric"><?=$totalActive?> / <?=count($list)?></div></div>
  <div class="card"><span class="muted">مجموع بازدید</span><div class="metric"><?=number_format($totalImpr)?></div></div>
  <div class="card"><span class="muted">مجموع کلیک</span><div class="metric"><?=number_format($totalClicks)?> <span class="muted" style="font-size:13px">(CTR <?=$totalImpr?round($totalClicks/$totalImpr*100,1):0?>%)</span></div></div>
</div>

<div class="card" style="margin-top:14px">
  <h3><?=$edit ? 'ویرایش تبلیغ' : 'تبلیغ جدید'?></h3>
  <form method="post" enctype="multipart/form-data">
    <?=csrf_field()?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?=e((string)($edit['id'] ?? ''))?>">
    <div class="formgrid">
      <div><label>عنوان</label><input name="title" value="<?=e($edit['title'] ?? '')?>" required></div>
      <div><label>لینک مقصد</label><input name="target_url" type="url" value="<?=e($edit['target_url'] ?? '')?>" placeholder="https://..."></div>

      <div>
        <label>جایگاه نمایش</label>
        <select name="placement">
          <?php foreach (ad_placements() as $val => $label): ?>
            <option value="<?=e($val)?>" <?=($edit['placement'] ?? 'pre_connect')===$val?'selected':''?>><?=e($label)?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label>زمان نمایش (ثانیه، ۰=قابل رد کردن فوری)</label><input name="display_seconds" type="number" min="0" max="60" value="<?=e((string)($edit['display_seconds'] ?? 4))?>"></div>

      <div><label>اولویت (عدد بزرگ‌تر = نمایش بیشتر در چرخش)</label><input name="priority" type="number" value="<?=e((string)($edit['priority'] ?? 0))?>"></div>
      <div class="full"><label>تصویر (JPG/PNG/WebP/GIF، حداکثر ۵MB)</label><input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" <?=$edit ? '' : 'required'?>></div>

      <div><label>شروع نمایش (اختیاری)</label><input name="starts_at" type="datetime-local" value="<?=e(!empty($edit['starts_at']) ? str_replace(' ', 'T', substr($edit['starts_at'],0,16)) : '')?>"></div>
      <div><label>پایان نمایش (اختیاری)</label><input name="ends_at" type="datetime-local" value="<?=e(!empty($edit['ends_at']) ? str_replace(' ', 'T', substr($edit['ends_at'],0,16)) : '')?>"></div>

      <div><label><input style="width:auto" type="checkbox" name="active" <?=!$edit || $edit['active'] ? 'checked' : ''?>> فعال</label></div>
      <div><button><?=$edit ? 'ذخیره تغییرات' : 'ذخیره تبلیغ'?></button> <?php if ($edit): ?><a class="btn secondary" href="<?=e(app_path('/admin/ads.php'))?>">انصراف از ویرایش</a><?php endif; ?></div>
    </div>
  </form>
</div>

<div class="card" style="margin-top:14px">
  <h3>تبلیغات</h3>
  <table>
    <thead><tr><th>تصویر</th><th>عنوان</th><th>جایگاه</th><th>زمان‌بندی</th><th>بازدید</th><th>کلیک</th><th>CTR</th><th>وضعیت</th><th>عملیات</th></tr></thead>
    <tbody>
    <?php foreach ($list as $a):
        $schedule = ad_schedule_status($a['starts_at'], $a['ends_at']);
        $ctr = $a['impressions'] ? round($a['clicks'] / $a['impressions'] * 100, 1) : 0;
    ?>
    <tr>
      <td><img class="adimg" src="<?=e(app_path('/' . $a['image_path']))?>" alt=""></td>
      <td><?=e($a['title'])?><br><small class="muted"><?=e((string)$a['display_seconds'])?> ثانیه · اولویت <?=e((string)$a['priority'])?></small></td>
      <td><?=e(ad_placements()[$a['placement']] ?? $a['placement'])?></td>
      <td>
        <?php if ($schedule === 'scheduled'): ?><span class="badge">زمان‌بندی‌شده</span>
        <?php elseif ($schedule === 'expired'): ?><span class="badge bad">منقضی</span>
        <?php else: ?><span class="muted">بدون محدودیت</span>
        <?php endif; ?>
        <?php if ($a['starts_at'] || $a['ends_at']): ?>
          <br><small class="muted"><?=e($a['starts_at'] ? substr($a['starts_at'],0,16) : '—')?> تا <?=e($a['ends_at'] ? substr($a['ends_at'],0,16) : '—')?></small>
        <?php endif; ?>
      </td>
      <td><?=number_format((int)$a['impressions'])?></td>
      <td><?=number_format((int)$a['clicks'])?></td>
      <td><?=$ctr?>%</td>
      <td>
        <?php if (!$a['active']): ?><span class="badge">خاموش</span>
        <?php elseif ($schedule === 'live'): ?><span class="badge good">فعال</span>
        <?php else: ?><span class="badge bad"><?=$schedule==='scheduled'?'زمان‌بندی‌شده':'منقضی'?></span>
        <?php endif; ?>
      </td>
      <td>
        <div class="actions">
          <a class="btn secondary" href="?edit=<?=$a['id']?>">ویرایش</a>
          <form method="post"><?=csrf_field()?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=$a['id']?>"><button class="btn secondary"><?=$a['active']?'خاموش کردن':'روشن کردن'?></button></form>
          <form method="post" onsubmit="return confirm('حذف شود؟')"><?=csrf_field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$a['id']?>"><button class="btn bad">حذف</button></form>
        </div>
      </td>
    </tr>
    <?php endforeach; if (!$list): ?>
    <tr><td colspan="9" class="muted">هنوز تبلیغی ثبت نشده.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/_footer.php';
