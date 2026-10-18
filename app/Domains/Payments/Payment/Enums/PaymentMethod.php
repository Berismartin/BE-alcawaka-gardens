<?php

namespace App\Domains\Payments\Payment\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Pos = 'pos';
    case Card = 'card';
    case MtnMomo = 'mtn_momo';
    case AirtelMoney = 'airtel_money';
    case BankTransfer = 'bank_transfer';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash at desk',
            self::Pos => 'Card (POS)',
            self::Card => 'Card online',
            self::MtnMomo => 'MTN MoMo',
            self::AirtelMoney => 'Airtel Money',
            self::BankTransfer => 'Bank transfer',
        };
    }

    /** What a website guest can choose. Cash means paying at the front desk or reception on arrival. */
    public static function onlineChoices(): array
    {
        return [self::MtnMomo->value, self::AirtelMoney->value, self::Card->value, self::Cash->value];
    }

    /** Methods staff can record by hand at the desk. Online methods arrive from the gateways. */
    public static function deskMethods(): array
    {
        return [self::Cash, self::Pos, self::MtnMomo, self::AirtelMoney, self::BankTransfer];
    }
}
