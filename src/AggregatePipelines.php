<?php

namespace Sokil\Mongo;

class AggregatePipelines
{
    private $pipelines = array();
    
    /**
     * @var \Sokil\Mongo\Collection
     */
    private $collection;
    
    public function __construct(Collection $collection)
    {
        $this->collection = $collection;
    }
    
    private function _add($operator, $pipeline) {
        $lastIndex = count($this->pipelines) - 1;
        
        if(!$this->pipelines || !isset($this->pipelines[$lastIndex][$operator]) || $operator == '$group') {
            $this->pipelines[] = array($operator => $pipeline);
        }
        else {
            $this->pipelines[$lastIndex][$operator] = array_merge($this->pipelines[$lastIndex][$operator], $pipeline);
        }
    }

    /**
     * Filter documents by expression
     *
     * @param array|\Sokil\Mongo\Expression $expression
     * @return \Sokil\Mongo\AggregatePipelines
     * @throws \Sokil\Mongo\Exception
     */
    public function match($expression) {
        if(is_callable($expression)) {
            $expressionConfigurator = $expression;
            $expression = new Expression();
            call_user_func($expressionConfigurator, $expression);
            $expression = $expression->toArray();
        } elseif(!is_array($expression)) {
            throw new Exception('Must be array or instance of Expression');
        }
        
        $this->_add('$match', $expression);
        return $this;
    }
    
    public function project(array $pipeline) {
        $this->_add('$project', $pipeline);
        return $this;
    }
    
    public function group(array $pipeline) {
        
        if(!isset($pipeline['_id'])) {
            throw new Exception('Group field in _id key must be specified');
        }
        
        $this->_add('$group', $pipeline);
        return $this;
    }
    
    public function sort(array $pipeline) {
        $this->_add('$sort', $pipeline);
        return $this;
    }
    
    public function toArray() {
        return $this->pipelines;
    }
    
    public function limit($limit) {
        $this->_add('$limit', (int) $limit);
        return $this;
    }
    
    public function skip($skip) {
        $this->_add('$skip', (int) $skip);
        return $this;
    }
    
    public function aggregate()
    {        
        return $this->collection->aggregate($this);
    }
    
    public function __toString()
    {
        return json_encode($this->pipelines);
    }
}
