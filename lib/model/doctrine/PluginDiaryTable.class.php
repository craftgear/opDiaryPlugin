<?php

/**
 * This file is part of the OpenPNE package.
 * (c) OpenPNE Project (http://www.openpne.jp/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file and the NOTICE file that were distributed with this source code.
 */

/**
 * PluginDiaryTable
 *
 * @package    opDiaryPlugin
 * @author     Rimpei Ogawa <ogawa@tejimaya.com>
 */
abstract class PluginDiaryTable extends Doctrine_Table
{
  const PUBLIC_FLAG_OPEN    = 4;
  const PUBLIC_FLAG_SNS     = 1;
  const PUBLIC_FLAG_FRIEND  = 2;
  const PUBLIC_FLAG_PRIVATE = 3;

  protected static $publicFlags = array(
    self::PUBLIC_FLAG_OPEN    => 'All Users on the Web',
    self::PUBLIC_FLAG_SNS     => 'All Members',
    self::PUBLIC_FLAG_FRIEND  => 'My Friends',
    self::PUBLIC_FLAG_PRIVATE => 'Private',
  );

  public function getPublicFlags()
  {
    if (!sfConfig::get('app_op_diary_plugin_is_open', false))
    {
      unset(self::$publicFlags[self::PUBLIC_FLAG_OPEN]);
    }

    return array_map(array(sfContext::getInstance()->getI18N(), '__'), self::$publicFlags);
  }

  public function getDiaryList($limit = 5, $publicFlag = self::PUBLIC_FLAG_SNS)
  {
    $q = $this->getOrderdQuery();
    $this->addPublicFlagQuery($q, $publicFlag);
    $q->limit($limit);

    return $q->execute();
  }

  public function getDiaryPager($page = 1, $size = 20, $publicFlag = self::PUBLIC_FLAG_SNS)
  {
    $q = $this->getOrderdQuery();
    $this->addPublicFlagQuery($q, $publicFlag);

    return $this->getPager($q, $page, $size);
  }

  public function getMemberDiaryList($memberId, $limit = 5, $myMemberId = null)
  {
    $q = $this->getOrderdQuery();
    $this->addMemberQuery($q, $memberId, $myMemberId);
    $q->limit($limit);

    return $q->execute();
  }

  public function getMemberDiaryPager($memberId, $page = 1, $size = 20, $myMemberId = null, $year = null, $month = null, $day = null)
  {
    $q = $this->getOrderdQuery();
    $this->addMemberQuery($q, $memberId, $myMemberId);

    if ($year && $month)
    {
      $this->addDateQuery($q, $year, $month, $day);
    }

    return $this->getPager($q, $page, $size);
  }

  public function getMemberDiaryDays($memberId, $myMemberId, $year, $month)
  {
    $days = array();

    $q = $this->createQuery()->select('created_at');
    $this->addMemberQuery($q, $memberId, $myMemberId);
    $this->addDateQuery($q, $year, $month);

    $result = $q->execute();
    foreach ($result as $row)
    {
      $day = date('j', strtotime($row['created_at']));
      $days[$day] = true;
    }

    return $days;
  }

  public function getFriendDiaryList($memberId, $limit = 5)
  {
    $q = $this->getOrderdQuery();
    $this->addFriendQuery($q, $memberId);
    $q->limit($limit);

    return $q->execute();
  }

  public function getFriendDiaryPager($memberId, $page = 1, $size = 20)
  {
    $q = $this->getOrderdQuery();
    $this->addFriendQuery($q, $memberId);

    return $this->getPager($q, $page, $size);
  }

  protected function getPager(Doctrine_Query $q, $page, $size)
  {
    $pager = new sfDoctrinePager('Diary', $size);
    $pager->setQuery($q);
    $pager->setPage($page);

    return $pager;
  }

  protected function getOrderdQuery()
  {
    return $this->createQuery()->orderBy('created_at DESC');
  }

  protected function addMemberQuery(Doctrine_Query $q, $memberId, $myMemberId)
  {
    $q->andWhere('member_id = ?', $memberId);
    $this->addPublicFlagQuery($q, self::getPublicFlagByMemberId($memberId, $myMemberId));
  }

  protected function addFriendQuery(Doctrine_Query $q, $memberId)
  {
    $friendIds = Doctrine::getTable('MemberRelationship')->getFriendMemberIds($memberId, 5);

    $q->andWhereIn('member_id', $friendIds);
    $this->addPublicFlagQuery($q, self::PUBLIC_FLAG_FRIEND);
  }

  public function addPublicFlagQuery(Doctrine_Query $q, $flag)
  {
    if ($flag === self::PUBLIC_FLAG_PRIVATE)
    {
      return;
    }

    $flags = self::getViewablePublicFlags($flag);
    if (1 === count($flags))
    {
      $q->andWhere('public_flag = ?', array_shift($flags));
    }
    else
    {
      $q->andWhereIn('public_flag', $flags);
    }
  }

  protected function addDateQuery(Doctrine_Query $q, $year, $month, $day = null)
  {
    if ($day)
    {
      $begin = sprintf('%4d-%02d-%02d 00:00:00', $year, $month, $day);
      $end   = sprintf('%4d-%02d-%02d 00:00:00', $year, $month, $day+1);
    }
    else
    {
      $begin = sprintf('%4d-%02d-01 00:00:00', $year, $month);
      $end   = sprintf('%4d-%02d-01 00:00:00', $year, $month+1);
    }

    $q->andWhere('created_at >= ?', $begin);
    $q->andWhere('created_at < ?', $end);
  }

  public function getPublicFlagByMemberId($memberId, $myMemberId, $forceFlag = null)
  {
    if ($forceFlag)
    {
      return $forceFlag;
    }

    if ($memberId == $myMemberId)
    {
      return self::PUBLIC_FLAG_PRIVATE;
    }

    $relation = Doctrine::getTable('MemberRelationship')->retrieveByFromAndTo($myMemberId, $memberId);
    if ($relation && $relation->isFriend())
    {
      return self::PUBLIC_FLAG_FRIEND;
    }
    else
    {
      return self::PUBLIC_FLAG_SNS;
    }
  }

  public function getViewablePublicFlags($flag)
  {
    $flags = array();
    switch ($flag)
    {
      case self::PUBLIC_FLAG_PRIVATE:
        $flags[] = self::PUBLIC_FLAG_PRIVATE;
      case self::PUBLIC_FLAG_FRIEND:
        $flags[] = self::PUBLIC_FLAG_FRIEND;
      case self::PUBLIC_FLAG_SNS:
        $flags[] = self::PUBLIC_FLAG_SNS;
      case self::PUBLIC_FLAG_OPEN:
        $flags[] = self::PUBLIC_FLAG_OPEN;
        break;
    }

    return $flags;
  }

  public function getPreviousDiary(Diary $diary, $myMemberId)
  {
    $q = $this->createQuery()
      ->andWhere('member_id = ?', $diary->getMemberId())
      ->andWhere('id < ?', $diary->getId())
      ->orderBy('id DESC');
    $this->addPublicFlagQuery($q, $this->getPublicFlagByMemberId($diary->getMemberId(), $myMemberId));

    return $q->fetchOne();
  }

  public function getNextDiary(Diary $diary, $myMemberId)
  {
    $q = $this->createQuery()
      ->andWhere('member_id = ?', $diary->getMemberId())
      ->andWhere('id > ?', $diary->getId())
      ->orderBy('id ASC');
    $this->addPublicFlagQuery($q, $this->getPublicFlagByMemberId($diary->getMemberId(), $myMemberId));

    return $q->fetchOne();
  }
}