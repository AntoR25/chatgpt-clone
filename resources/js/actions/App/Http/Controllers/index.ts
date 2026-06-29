import ChatController from './ChatController'
import Settings from './Settings'
const Controllers = {
    ChatController: Object.assign(ChatController, ChatController),
Settings: Object.assign(Settings, Settings),
}

export default Controllers