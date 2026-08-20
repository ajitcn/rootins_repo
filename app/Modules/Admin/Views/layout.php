<?= $this->extend('templates/theme1')?>
 
 <?= $this->section('side-menu')?>
 	<?= $this->include(ADMIN_VIEW. 'parts/side_menu') ?>
 <?= $this->endSection()?>

  <?= $this->section('top-menu')?>
 	<?= $this->include(ADMIN_VIEW. 'parts/top_menu') ?>
 <?= $this->endSection()?>