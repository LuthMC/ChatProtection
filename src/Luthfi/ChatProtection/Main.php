<?php

namespace Luthfi\ChatProtection;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;

class Main extends PluginBase implements Listener {

    private $eventListener;
    private $messages;
    
    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->messages = new Config($this->getDataFolder() . "messages.yml", Config::YAML);
        $this->eventListener = new EventListener($this);
        $this->getServer()->getPluginManager()->registerEvents($this->eventListener, $this);
        $this->loadMessages();
        $this->getLogger()->info("ChatProtection Enabled");
    }

    private function loadMessages(): void {
        $this->messages = $this->messages->getAll();
    }

    private function getMessage(string $key, array $replacements = []): string {
        $message = $this->messages[$key] ?? $key;
        $prefix = $this->messages['prefix'] ?? "[ChatProtection] ";
        $message = str_replace("{prefix}", $prefix, $message);
        foreach ($replacements as $search => $replace) {
            $message = str_replace($search, $replace, $message);
        }
        return $message;
    }
    
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        switch ($command->getName()) {
            case "lock":
                if ($sender->hasPermission("chatprotection.lock")) {
                    $this->eventListener->lockChat();
                    $this->getServer()->broadcastMessage($this->getMessage("chat_locked_by_admin"));
                    return true;
                }
                $sender->sendMessage($this->getMessage("no_permission"));
                return false;

            case "unlock":
                if ($sender->hasPermission("chatprotection.unlock")) {
                    $this->eventListener->unlockChat();
                    $this->getServer()->broadcastMessage($this->getMessage("chat_unlocked_by_admin"));
                    return true;
                }
                $sender->sendMessage($this->getMessage("no_permission"));
                return false;

            case "clearchat":
                if ($sender->hasPermission("chatprotection.clearchat")) {
                    $this->clearChat();
                    $sender->sendMessage($this->getMessage("chat_cleared"));
                    return true;
                }
                $sender->sendMessage($this->getMessage("no_permission"));
                return false;
        }
        return false;
    }

    private function clearChat(): void {
        $clearMessage = str_repeat("\n", 100);
        foreach ($this->getServer()->getOnlinePlayers() as $player) {
            $player->sendMessage($clearMessage);
        }
    }

    public function onDisable(): void {
        $this->getLogger()->info("ChatProtection Disabled");
    }
}
