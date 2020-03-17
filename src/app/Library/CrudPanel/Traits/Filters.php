<?php

namespace Backpack\CRUD\app\Library\CrudPanel\Traits;

use Symfony\Component\HttpFoundation\ParameterBag;
use Backpack\CRUD\app\Library\CrudPanel\CrudFilter;
use Illuminate\Support\Collection;
use Illuminate\Support\Arr;

trait Filters
{
    /**
     * @return bool
     */
    public function filtersEnabled()
    {
        return $this->filters() && $this->filters() != [];
    }

    /**
     * @return bool
     */
    public function filtersDisabled()
    {
        return $this->filters() == [] || $this->filters() == null;
    }

    public function enableFilters()
    {
        if ($this->filtersDisabled()) {
            $this->setOperationSetting('filters', new Collection());
        }
    }

    public function disableFilters()
    {
        $this->setOperationSetting('filters', []);
    }

    public function clearFilters()
    {
        $this->setOperationSetting('filters', new Collection());
    }

    /**
     * Add a filter to the CRUD table view.
     *
     * @param array               $options       Name, type, label, etc.
     * @param bool|array|\Closure $values        The HTML for the filter.
     * @param bool|\Closure       $filterLogic   Query modification (filtering) logic when filter is active.
     * @param bool|\Closure       $fallbackLogic Query modification (filtering) logic when filter is not active.
     */
    public function addFilter($options, $values = false, $filterLogic = false, $fallbackLogic = false)
    {
        $filter = $this->addFilterToCollection($options, $values, $filterLogic, $fallbackLogic);

        // apply the filter logic
        $this->applyFilter($filter);
    }

    /**
     * Add a filter to the CrudPanel object using the Settings API.
     * The filter will NOT get applied.
     *
     * @param array               $options       Name, type, label, etc.
     * @param bool|array|\Closure $values        The HTML for the filter.
     * @param bool|\Closure       $filterLogic   Query modification (filtering) logic when filter is active.
     * @param bool|\Closure       $fallbackLogic Query modification (filtering) logic when filter is not active.
     */
    protected function addFilterToCollection($options, $values = false, $filterLogic = false, $fallbackLogic = false)
    {
        // if a closure was passed as "values"
        if (is_callable($values)) {
            // get its results
            $values = $values();
        }

        // enable the filters functionality
        $this->enableFilters();

        // check if another filter with the same name exists
        if (! isset($options['name'])) {
            abort(500, 'All your filters need names.');
        }
        if ($this->filters()->contains('name', $options['name'])) {
            abort(500, "Sorry, you can't have two filters with the same name.");
        }

        // add a new filter to the interface
        $filter = new CrudFilter($options, $values, $filterLogic, $fallbackLogic, $this);
        $this->setOperationSetting('filters', $this->filters()->push($filter));

        return $filter;
    }

    /**
     * Add a filter by specifying the entire CrudFilter object.
     * The filter logic does NOT get applied.
     * 
     * @param CrudFilter $object
     */
    public function addCrudFilter($object)
    {
        return $this->addFilterToCollection($object->options, $object->values, $object->logic, $object->fallbackLogic);
    }

    /**
     * Apply the filter.
     *
     * @param CrudFilter              $filter
     * @param ParameterBag|array|null $input
     */
    public function applyFilter(CrudFilter $filter, $input = null)
    {
        $filter->apply($input);
    }

    /**
     * @return array|\Illuminate\Support\Collection
     */
    public function filters()
    {
        return $this->getOperationSetting('filters') ?? collect();
    }

    /**
     * @param string $name
     *
     * @return null|CrudFilter
     */
    public function getFilter($name)
    {
        if ($this->filtersEnabled()) {
            return $this->filters()->firstWhere('name', $name);
        }
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasActiveFilter($name)
    {
        $crudFilter = $this->getFilter($name);

        return $crudFilter instanceof CrudFilter && $crudFilter->isActive();
    }

    /**
     * Modify the attributes of a filter.
     *
     * @param string $name          The filter name.
     * @param array  $modifications An array of changes to be made.
     *
     * @return CrudFilter The filter that has suffered modifications, for daisychaining methods.
     */
    public function modifyFilter($name, $modifications)
    {
        $filter = $this->filters()->firstWhere('name', $name);

        if (! $filter) {
            abort(500, 'CRUD Filter "'.$name.'" not found. Please check the filter exists before you modify it.');
        }

        if (is_array($modifications)) {
            foreach ($modifications as $key => $value) {
                $filter->{$key} = $value;
            }
        }

        return $filter;
    }

    public function removeFilter($name)
    {
        $strippedCollection = $this->filters()->reject(function ($filter) use ($name) {
            return $filter->name == $name;
        });

        $this->setOperationSetting('filters', $strippedCollection);
    }

    public function removeAllFilters()
    {
        $this->setOperationSetting('filters', new Collection());
    }

    /**
     * Check if a filter exists, by any given attribute.
     *
     * @param  string  $attribute   Attribute name on that filter definition array.
     * @param  string  $value       Value of that attribute on that filter definition array.
     * @return bool
     */
    public function hasFilterWhere($attribute, $value)
    {
        return $this->filters()->contains($attribute, $value);
    }

    /**
     * Get the first filter where a given attribute has the given value.
     *
     * @param  string  $attribute   Attribute name on that filter definition array.
     * @param  string  $value       Value of that attribute on that filter definition array.
     * @return bool
     */
    public function firstFilterWhere($attribute, $value)
    {
        return $this->filters()->firstWhere($attribute, $value);
    }

    /**
     * Create and return a CrudFilter object for that attribute.
     *
     * Enables developers to use a fluent syntax to declare their filters,
     * in addition to the existing options:
     * - CRUD::addFilter(['name' => 'price', 'type' => 'range'], false, function($value) {});
     * - CRUD::filter('price')->type('range')->whenActive(function($value) {});
     *
     * And if the developer uses the CrudField object as Field in his CrudController:
     * - Filter::name('price')->type('range')->whenActive(function($value) {});
     *
     * @param  string $name The name of the column in the db, or model attribute.
     * @return CrudField
     */
    public function filter($name)
    {
        return new CrudFilter(compact('name'), null, null, null, $this);
    }
}