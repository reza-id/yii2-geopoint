Yii2-geopoint
============

ActiveRecord inspired by [yii2-spatial](https://github.com/sjaakp/yii2-spatial) but made simpler only to use specific spatial datatype: POINT.  
Transform the internal [MySQL format](https://dev.mysql.com/doc/refman/5.5/en/spatial-datatypes.html) to simple coordinate text after finding, and vice versa before storing.

**Yii2-geopoint** can also be used to find the model or models which are nearest to a given location.

**Notice that this extension can be used with `MySQL >= 5.6.1`, `MariaDB >= 5.3.3`, and `PostgreSQL >= 9.1`.**

## Installation ##

Install **Yii2-geopoint** with [Composer](https://getcomposer.org/). Either add the following to the require section of your `composer.json` file:

`"reza-id/yii2-geopoint": "*"` 

Or run:

`$ php composer.phar require reza-id/yii2-geopoint "*"` 

You can manually install **Yii2-geopoint** by [downloading the source in ZIP-format](https://github.com/reza-id/yii2-geopoint/archive/master.zip).

## Usage ##

Create spatial indexed table using migration:

	$this->createTable('{{%place}}', [
		'id' => $this->primaryKey(),
		'name' => $this->string(125)->notNull(),
		'location' => 'POINT NOT NULL',
	], $tableOptions);

	if ($this->db->driverName === 'mysql') {
		$this->execute('CREATE SPATIAL INDEX `idx-place-location` ON '.'{{%place}}(location);');
	} elseif ($this->db->driverName === 'pgsql') {
		$this->execute('CREATE INDEX "idx-place-location" ON '.'{{%place}} USING GIST(location);');
	}


Use a `rezaid\geopoint\ActiveRecord` as base class for your models, like so:

	<?php
	namespace app\models;

	use rezaid\geopoint\ActiveRecord;

	class MyModel extends ActiveRecord
	{
	    // ...
	}


**Notice:** if you override `find()` in a `rezaid\geopoint\ActiveRecord`-derived class, be sure to return a `rezaid\geopoint\ActiveQuery` and not an 'ordinary' `yii\db\ActiveQuery`.

## ActiveQuery method ##

#### nearest() ####

    public function nearest($from, $attribute, $radius, $unit)

Change the query so that it finds the model(s) nearest to the point given by `$from`.

- `$from` - `string`:  location in the form `<lng>,<lat>` (two `floats`).
- `$attribute` - `string` attribute name of `Point` in the model.
- `$radius` - `number` search radius in kilometers or miles. Default `100`.
- `$unit` - `string` unit value `km` for kilometers or `mil` for miles. Default `km`.

Example usages:

    $here = '4.9,52.3';     // longitude and latitude of my place
     

	$nearestModel = <model>::find()->nearest($here, <attributeName>, 200, 'mil')->one();    // search radius is 200 miles
    
	$fiveNearestModels =  <model>::find()->nearest($here, <attributeName>)->limit(5)->all();	// search radius is 100 km (default)
    
	$dataProvider = new ActiveDataProvider([ 'query' => <model>::find()->nearest($here, <attributeName>) ]);

## ActiveRecord method ##

#### getDistance() ####

Get the distance from given location in the ActiveQuery method `nearest()`, if you want to display the distance in RESTful API, add this as new field in your model:

	<?php
	namespace app\models;

	use rezaid\geopoint\ActiveRecord;

	class MyModel extends ActiveRecord
	{
		
		// ...

		public function fields()
		{
			$fields = parent::fields();

			$fields['distance'] = function ($model) {
				return $model->getDistance();
			};

			return $fields;
		}

		// ...

	}


Example rest controller:

	<?php
	namespace app\controllers;

	use Yii;
	use yii\rest\ActiveController;

	class PlaceController extends ActiveController
	{
		public $modelClass = 'app\models\MyModel';

		public function actionSearch()
		{
			$from = Yii::$app->request->get('from');
			$model = new $this->modelClass;
			$query = $model->find();

			if (!empty($from)) {
				$query->nearest($from, 'location', 200);
			}

			try {
				$provider = new \yii\data\ActiveDataProvider([
					'query' => $query,
				]);
			} catch (Exception $ex) {
				throw new \yii\web\HttpException(500, 'Internal server error');
			}

			if ($provider->getCount() <= 0) {
				throw new \yii\web\HttpException(404, 'No entries found');
			} else {
				return $provider;
			}
		}
	}