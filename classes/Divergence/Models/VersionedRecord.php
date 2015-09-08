<?php
namespace Divergence\Models;

/*	Convert this whole thing to a trait
 *
 */

abstract class VersionedRecord extends ActiveRecord
{


	// configure ActiveRecord
	static public $fields = array(
		'RevisionID' => array(
			'columnName' => 'RevisionID'
			,'type' => 'integer'
			,'unsigned' => true
			,'notnull' => false
		)
	);
	
	static public $relationships = array(
		'OldVersions' => array(
			'type' => 'history'
			,'order' => array('RevisionID' => 'DESC')
		)
	);
	


	// configure VersionedRecord
	static public $historyTable;


	/*
	 * Implement history relationship
	 */
	/*public function getValue($name)
	{
		switch($name)
		{
			case 'RevisionID':
			{
				return isset($this->_record['RevisionID']) ? $this->_record['RevisionID'] : null;
			}
			default:
			{
				return parent::getValue($name);
			}
		}
	}*/
	
	static protected function _initRelationship($relationship, $options)
	{
		if($options['type'] == 'history')
		{
			if(empty($options['class']))
				$options['class'] = get_called_class();
		}

		return parent::_initRelationship($relationship, $options);
	}

	protected function _getRelationshipValue($relationship)
	{
		if(!isset($this->_relatedObjects[$relationship]))
		{
			$rel = static::$_classRelationships[get_called_class()][$relationship];

			if($rel['type'] == 'history')
			{
				$this->_relatedObjects[$relationship] = $rel['class']::getRevisionsByID($this->__get(static::$primaryKey), $rel);
			}
		}
		
		return parent::_getRelationshipValue($relationship);
	}
	
	protected function _setFieldValue($field, $value)
	{
		// ignore setting versioning fields
		if(array_key_exists($field, self::$fields))
			return false;
		else
			return parent::_setFieldValue($field, $value);
	}	
	/*
	 * Implement specialized getters
	 */
	static public function getRevisionsByID($ID, $options = array())
	{
		$options['conditions'][static::$primaryKey] = $ID;
		
		return static::getRevisions($options);
	}

	static public function getRevisions($options = array())
	{
		return static::instantiateRecords(static::getRevisionRecords($options));
	}
	
	static public function getRevisionRecords($options = array())
	{
		$options = static::prepareOptions($options, array(
			'indexField' => false
			,'conditions' => array()
			,'order' => false
			,'limit' => false
			,'offset' => 0
		));
				
		$query = 'SELECT * FROM `%s` WHERE (%s)';
		$params = array(
			static::$historyTable
			, count($options['conditions']) ? join(') AND (', static::_mapConditions($options['conditions'])) : 1
		);
		
		if($options['order'])
		{
			$query .= ' ORDER BY ' . join(',', static::_mapFieldOrder($options['order']));
		}
		
		if($options['limit'])
		{
			$query .= sprintf(' LIMIT %u,%u', $options['offset'], $options['limit']);
		}
		
		
		if($options['indexField'])
		{
			return DB::table(static::_cn($options['indexField']), $query, $params);
		}
		else
		{
			return DB::allRecords($query, $params);
		}
	}
	
	
	/*
	 * Create new revisions on destroy
	 */
	public function destroy($createRevision = true)
    {
    	if($createRevision)
    	{
    		// save a copy to history table
			$this->Created = time();
			$this->CreatorID = $_SESSION['User'] ? $_SESSION['User']->ID : null;
			if ($this->_fieldExists('end_date') )
			{
				$this->end_date = date('Y-m-d');
			}
    		$recordValues = $this->_prepareRecordValues();
    		$set = static::_mapValuesToSet($recordValues);
    	
    		DB::nonQuery(
    				'INSERT INTO `%s` SET %s'
    				, array(
    						static::$historyTable
    						, join(',', $set)
    				)
    		);
    	}
    	
    	$return = parent::destroy();
    	 
    }
    
    
	/*
	 * Create new revisions on save
	 */
	public function save($deep = true, $createRevision = true)
	{
		$wasDirty = false;
		
		if($this->isDirty && $createRevision)
		{
			// update creation time / user
			$this->Created = time();
			$this->CreatorID = $_SESSION['User'] ? $_SESSION['User']->ID : null;
			
			$wasDirty = true;
		}
	
		// save record as usual
		$return = parent::save($deep);

		if($wasDirty && $createRevision)
		{
			// save a copy to history table
			$recordValues = $this->_prepareRecordValues();
			$set = static::_mapValuesToSet($recordValues);
	
			DB::nonQuery(
				'INSERT INTO `%s` SET %s'
				, array(
					static::$historyTable
					, join(',', $set)
				)
			);
		}
		
	}


}