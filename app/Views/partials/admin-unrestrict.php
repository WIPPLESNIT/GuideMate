<?php
/** @var int $id @var string $name @var string $return @var string $buttonClass @var string $label */
$id = (int) $id;
$name = (string) ($name ?? 'this partner');
$return = (string) ($return ?? '/admin/users');
$buttonClass = (string) ($buttonClass ?? 'btn btn-primary btn-sm');
$label = (string) ($label ?? 'Unrestrict account');
?>
<form method="post"
      action="<?= e(url('/admin/users/' . $id . '/clear-warning')) ?>"
      class="js-unrestrict"
      data-name="<?= e($name) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="return" value="<?= e($return) ?>">
    <button type="submit" class="<?= e($buttonClass) ?>"><?= e($label) ?></button>
</form>
