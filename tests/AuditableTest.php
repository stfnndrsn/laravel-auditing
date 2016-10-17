<?php

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;

class AuditableTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $db = new DB;

        $db->addConnection([
            'driver'    => 'sqlite',
            'database'  => ':memory:',
        ]);

        $db->bootEloquent();

        $db->setAsGlobal();

        $this->createSchema();
    }

    public function tearDown()
    {
        $this->schema()->drop('users');
        Mockery::close();
    }

    protected function createSchema()
    {
        $this->schema()->create('users', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('email');
            $table->timestamps();
        });

        $this->schema()->create('audits', function ($table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('auditable');
            $table->text('old')->nullable();
            $table->text('new')->nullable();
            $table->string('user_id')->nullable();
            $table->string('route')->nullable();
            $table->ipAddress('ip_address', 45)->nullable();
            $table->timestamp('created_at');
        });
    }

    public function testBasicAuditing()
    {
        $container = Container::setInstance(new Container);

        $model = ModelUserTest::forceCreate([
            'name' => 'anterio', 'email' => 'anteriovieira@gmail.com'
        ]);

        $model->name = 'anteriovieria';
        $model->save();

        $this->assertEquals(1, ModelUserTest::count());
        $this->assertEquals(2, $model->audits);
    }

    public function testItGetsTransformAudit()
    {
        $attributes = ['name' => 'Anterio', 'password' => '12345'];

        $model = new ModelAuditableTestRaw();
        $result = $model->transformAudit($attributes);

        $this->assertEquals($attributes, $result);
    }

    public function testWithAuditRespectsWithoutHidden()
    {
        $attributes = ['name' => 'Anterio', 'password' => '12345'];

        $auditable = new ModelAuditableTestRaw();

        $result = $auditable->cleanHiddenAuditAttributes($attributes);

        $this->assertEquals($attributes, $result);
    }

    public function testWithAuditRespectsWithHidden()
    {
        $attributes = ['name' => 'Anterio', 'password' => '12345'];

        $auditable = new ModelAuditableTestCustomsValues();

        $result = $auditable->cleanHiddenAuditAttributes($attributes);

        $this->assertEquals(['name' => 'Anterio', 'password' => null], $result);
    }

    public function testItGetsLogCustomMessage()
    {
        $logCustomMessage = ModelAuditableTestCustomsValues::$logCustomMessage;

        $this->assertEquals('{user.name} {type} a post {elapsed_time}', $logCustomMessage);
    }

    protected function connection($connection = 'default')
    {
        return Model::getConnectionResolver()->connection($connection);
    }

    protected function schema($connection = 'default')
    {
        return $this->connection($connection)->getSchemaBuilder();
    }
}

class ModelUserTest extends Model
{
    use Auditable;

    protected $table = 'users';
}

class ModelAuditableTestRaw
{
    use Auditable;
}

class ModelAuditableTestCustomsValues extends Model
{
    use Auditable;

    protected $hidden = ['password'];

    protected $auditRespectsHidden = true;

    public static $logCustomMessage = '{user.name} {type} a post {elapsed_time}';
}

class ModelAuditableTestConfigs
{
    use Auditable;

    public static $auditRespectsHidden = true;
}
