<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;

/**
 * Chats Model
 *
 * @method \App\Model\Entity\Chat newEmptyEntity()
 * @method \App\Model\Entity\Chat newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\Chat[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Chat get($primaryKey, $options = [])
 * @method \App\Model\Entity\Chat findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\Chat patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Chat[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Chat|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Chat saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Chat[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Chat[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\Chat[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Chat[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */

class ChatsTable extends Table
{
    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('chats');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
        $this->belongsTo('UserFrom', [
            'className' => 'Users',
            'foreignKey' => 'user_from',
            'propertyName' => 'UserFrom',
        ]);
        $this->belongsTo('UserTo', [
            'className' => 'Users',
            'foreignKey' => 'user_to',
            'propertyName' => 'UserTo',

        ]);
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->integer('user_from')
            ->requirePresence('user_from', 'create')
            ->notEmptyString('user_from');

        $validator
            ->integer('user_to')
            ->requirePresence('user_to', 'create')
            ->notEmptyString('user_to');

        $validator
            ->scalar('content')
            ->requirePresence('content', 'create')
            ->notEmptyString('content');

        return $validator;
    }

    /**
     * lấy danh sách các người mà user đã nhắn tin
     */
    public function getAllChatsOfUser($user_id)
    {
        $query1 = $this->find('all')
            ->select(['user_to'])
            ->where(['chats.user_from' => $user_id]);
        $query2 = $this->find('all')
            ->select(['user_from'])
            ->where(['chats.user_to' => $user_id]);

        $userTable = TableRegistry::getTableLocator()->get('Users');
        $query3 = $userTable->find('all')->where(['id IN' => $query1]);
        $query4 = $userTable->find('all')->where(['id IN' => $query2]);
        $query3->union($query4);
        $query3 = $query3->where(['id <>' => $user_id]);

        return $query3->toArray();
    }

    /**
     * lấy danh sách chat của 2 user với nhau
     */
    public function getChatsOfUserFromAndUserTo($user_from, $user_to)
    {
        $query1 = $this->find('all')
            ->where(['chats.user_from IN' => [$user_from,$user_to], 'chats.user_to IN' => [$user_from,$user_to]])
            ->contain([
                'UserFrom',
                'UserTo',
            ])->orderAsc('chats.created');
        $query1 = $query1->orderAsc('chats.created');

        return $query1->toArray();
    }

    /**
     * Lấy id người mới nhắn tin
     */
    public function getLast($id)
    {
        $query = $this->find('all')
                        ->select(['user_to'])
                        ->where(['user_from' => $id]);

        return $query->first();
    }
}
