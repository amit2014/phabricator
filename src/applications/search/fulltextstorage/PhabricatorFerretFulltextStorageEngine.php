<?php

final class PhabricatorFerretFulltextStorageEngine
  extends PhabricatorFulltextStorageEngine {

  private $fulltextTokens = array();
  private $engineLimits;

  public function getEngineIdentifier() {
    return 'ferret';
  }

  public function getHostType() {
    return new PhabricatorMySQLSearchHost($this);
  }

  public function reindexAbstractDocument(
    PhabricatorSearchAbstractDocument $doc) {

    // NOTE: The Ferret engine indexes are rebuilt by an extension rather than
    // by the main fulltext engine, and are always built regardless of
    // configuration.

    return;
  }

  public function executeSearch(PhabricatorSavedQuery $query) {
    $all_objects = id(new PhutilClassMapQuery())
      ->setAncestorClass('PhabricatorFerretInterface')
      ->execute();

    $type_map = array();
    foreach ($all_objects as $object) {
      $phid_type = phid_get_type($object->generatePHID());

      $type_map[$phid_type] = array(
        'object' => $object,
        'engine' => $object->newFerretEngine(),
      );
    }

    $types = $query->getParameter('types');
    if ($types) {
      $type_map = array_select_keys($type_map, $types);
    }

    $offset = (int)$query->getParameter('offset', 0);
    $limit  = (int)$query->getParameter('limit', 25);

    $viewer = PhabricatorUser::getOmnipotentUser();

    $type_results = array();
    foreach ($type_map as $type => $spec) {
      $engine = $spec['engine'];
      $object = $spec['object'];

      // NOTE: For now, it's okay to query with the omnipotent viewer here
      // because we're just returning PHIDs which we'll filter later.

      $type_query = $engine->newConfiguredFulltextQuery(
        $object,
        $query,
        $viewer);

      $type_query
        ->setOrder('relevance')
        ->setLimit($offset + $limit);

      $results = $type_query->execute();
      $results = mpull($results, null, 'getPHID');
      $type_results[$type] = $results;
    }

    $list = array();
    foreach ($type_results as $type => $results) {
      $list += $results;
    }

    $result_slice = array_slice($list, $offset, $limit, true);
    return array_keys($result_slice);
  }

  public function indexExists() {
    return true;
  }

  public function getIndexStats() {
    return false;
  }

  public function getFulltextTokens() {
    return $this->fulltextTokens;
  }


}
