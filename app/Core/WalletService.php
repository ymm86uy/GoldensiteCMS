<?php
namespace App\Core;

final class WalletService
{
    public static function sendGift(\PDO $db, int $sender, int $receiver, int $giftId, string $giftName): void
    {
        if ($sender <= 0 || $receiver <= 0 || $sender === $receiver) throw new \InvalidArgumentException('Destinatario inválido.');
        $ownTransaction=!$db->inTransaction(); if ($ownTransaction) $db->beginTransaction();
        try {
            $stmt = $db->prepare('SELECT id FROM goldensite_dating_profiles WHERE id = ? LIMIT 1');
            $stmt->execute([$receiver]);
            if (!$stmt->fetchColumn()) throw new \RuntimeException('Destinatario inválido.');
            $stmt = $db->prepare('SELECT id, name, icon, price FROM goldensite_dating_gifts WHERE status = 1 AND ' . ($giftId > 0 ? 'id = ?' : 'name = ?') . ' LIMIT 1 FOR UPDATE');
            $stmt->execute([$giftId > 0 ? $giftId : $giftName]);
            $gift = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$gift || !preg_match('/^[0-9]+\.[0-9]{2}$/D', (string) $gift['price']) || (float) $gift['price'] <= 0) throw new \RuntimeException('Regalo inválido.');
            $stmt = $db->prepare('UPDATE goldensite_dating_profiles SET wallet_balance = wallet_balance - ? WHERE id = ? AND wallet_balance >= ?');
            $stmt->execute([$gift['price'], $sender, $gift['price']]);
            if ($stmt->rowCount() !== 1) throw new \RuntimeException('Saldo insuficiente.');
            $stmt = $db->prepare('INSERT INTO goldensite_dating_messages (sender_id, receiver_id, message, gift_icon) VALUES (?, ?, ?, ?)');
            $stmt->execute([$sender, $receiver, 'Te ha enviado un regalo virtual: ' . $gift['name'], $gift['icon']]);
            $stmt = $db->prepare("INSERT INTO goldensite_dating_transactions (user_id, type, amount, description) VALUES (?, 'regalo_virtual', ?, ?)");
            $stmt->execute([$sender, $gift['price'], 'Regalo #' . $gift['id'] . ' a usuario #' . $receiver]);
            if ($ownTransaction) $db->commit();
        } catch (\Throwable $error) {
            if ($ownTransaction&&$db->inTransaction()) $db->rollBack();
            throw $error;
        }
    }
}
