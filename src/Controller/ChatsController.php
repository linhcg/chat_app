<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * Chats Controller
 *
 * @property \App\Model\Table\ChatsTable $Chats
 * @method \App\Model\Entity\Chat[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class ChatsController extends AppController
{
    /**
     * phan quyen
     *
     * @return true|false
     */
    public function isAuthorized($user)
    {
        $action = $this->request->getParam('action');
        if ($user) {
            if (in_array($action, ['home', 'index', 'view', 'add', 'edit', 'delete', 'getChats','chat','addChat',])) {
                if ($user['role'] == 0) {
                    return true;
                }
            }
        }

        return parent::isAuthorized($user);
    }

    /**
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $this->viewBuilder()->setLayout('admin');
        $this->paginate = [
            'contain' => ['UserFrom', 'UserTo'],
        ];
        $chats = $this->paginate($this->Chats);

        $this->set(compact('chats'));
    }

    /**
     * View method
     *
     * @param string|null $id Chat id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $this->viewBuilder()->setLayout('admin');

        $chat = $this->Chats->get($id, [
            'contain' => ['UserFrom', 'UserTo'],
        ]);

        $this->set(compact('chat'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $this->viewBuilder()->setLayout('admin');

        $chat = $this->Chats->newEmptyEntity();
        if ($this->request->is('post')) {
            $chat = $this->Chats->patchEntity($chat, $this->request->getData());
            if ($this->Chats->save($chat)) {
                $this->Flash->success(__('The chat has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The chat could not be saved. Please, try again.'));
        }
        $userFrom = $this->Chats->UserFrom->find('list', ['limit' => 200]);
        $userTo = $this->Chats->UserTo->find('list', ['limit' => 200]);
        $this->set(compact('chat', 'userFrom', 'userTo'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Chat id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $chat = $this->Chats->get($id, [
            'contain' => [],
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $chat = $this->Chats->patchEntity($chat, $this->request->getData());
            if ($this->Chats->save($chat)) {
                $this->Flash->success(__('The chat has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The chat could not be saved. Please, try again.'));
        }
        $userFrom = $this->Chats->UserFrom->find('list', ['limit' => 200]);
        $userTo = $this->Chats->UserTo->find('list', ['limit' => 200]);
        $this->set(compact('chat', 'userFrom', 'userTo'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Chat id.
     * @return \Cake\Http\Response|null|void Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $chat = $this->Chats->get($id);
        if ($this->Chats->delete($chat)) {
            $this->Flash->success(__('The chat has been deleted.'));
        } else {
            $this->Flash->error(__('The chat could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * lấy danh sách chat của user
     *
     * @param string|null $id Chat id.
     * @return \Cake\Http\Response|null|void json
     */
    public function getChats($id)
    {
        $this->autoRender = false;
        $result = [];
        $chats = $this->Chats->getAllChatsOfUser($id);// danh sach cac nguoi ma user nhan tin
        $result['chats'] = $chats;
        $result['code'] = 200;
        $result['status'] = 'chats';
        if (empty($result)) {
            return;
        }

        return $this->jsonResponse($result);
    }

    /**
     * trang chur chat
     *
     * @param string|null $to Chat id.
     * @return \Cake\Http\Response|null|void Redirects to chat.
     */
    public function chat($to = null)
    {
        $userAuth = $this->Auth->user();
        $user = $this->Users->get($userAuth['id'], [
            'contain' => [],
        ]);
        if ($to == null) {
             $to = $this->Chats->getLast($user->id)->user_to;
        }
        $toUser = $this->Users->get($to);
        $chats = $this->Chats->getChatsOfUserFromAndUserTo($user->id, $to); // danh sach tin nhan cuar user voi user $to
        $usersChat = $this->Chats->getAllChatsOfUser($user->id); // danh sach cac nguoi ma user nhan tin
        $index = array_search($to, array_column($usersChat, 'id'));
        if ($index !== false) {
            array_splice($usersChat, $index, 1);
        }

        //   dd($usersChat);
        $this->set(compact(['toUser','user','chats','usersChat']));
    }

    /**
     * them chat
     *
     * @return \Cake\Http\Response|null|void json.
     */
    public function addChat()
    {
        $this->autoRender = false;
        $user = $this->Auth->user();
        // data: user_to , user_from, content
        $data = $this->request->getData('data');

        if (empty($data)) {
            return;
        }
        $chat = $this->Chats->newEntity(['user_from' => (int)$data['user_from'],
                                        'user_to' => (int)$data['user_to'],
                                        'content' => $data['content']]);
        $result = [];
        $result['chat'] = $chat;
        if ($this->Chats->save($chat)) {
            $result['code'] = 200;
            $result['status'] = 'new chat';
        } else {
            $result['code'] = 500;
            $result['status'] = 'error';
        }
        if (empty($result)) {
            return;
        }

        return $this->jsonResponse($result);
    }
}
