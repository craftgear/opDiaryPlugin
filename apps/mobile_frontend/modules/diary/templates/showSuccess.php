<?php include_page_title(__('Diary of %1%', array('%1%' => $diary->getMember()->getName())), $diary->getTitle()) ?>
<?php use_helper('Date') ?>

▼<?php echo format_datetime($diary->getCreatedAt(), 'f') ?>
<?php if ($diary->getMemberId() === $sf_user->getMemberId()): ?>
[<?php echo link_to(__('Edit'), 'diary/edit?id='.$diary->getId()) ?>][<?php echo link_to(__('Delete'), 'diary/delete?id='.$diary->getId()) ?>]
<?php endif; ?><br>

<?php echo nl2br($diary->getBody()) ?><br>

<?php foreach ($diary->getDiaryImages() as $image): ?>
View Image<br>
<?php endforeach; ?>

(<?php echo __('Public') ?>)<br>

<?php $comments = $diary->getDiaryComments() ?>
<?php if (count($comments)): ?>
<hr>
<center>
<?php echo __('Comments') ?><br>
</center>
<?php foreach ($comments as $comment): ?>
<hr>
▼<?php echo format_datetime($comment->getCreatedAt(), 'f') ?>
<?php if ($diary->getMemberId() === $sf_user->getMemberId() || $comment->getMemberId() === $sf_user->getMemberId()): ?>
[<?php echo link_to(__('Delete'), 'diary/deleteComment?id='.$comment->getId()) ?>]
<?php endif; ?><br>
<?php echo link_to($comment->getMember()->getName(), 'member/show?id='.$comment->getMemberId()) ?><br>
<?php echo nl2br($comment->getBody()) ?><br>
<?php endforeach; ?>
<?php endif; ?>

<hr>
<?php
$options = array('form' => array($form));
$title = __('Post a diary comment');
$options['url'] = 'diary/postComment?id='.$diary->getId();
$options['button'] = __('Save');
$options['isMultipart'] = true;
include_box('formDiaryComment', $title, '', $options);
?>
