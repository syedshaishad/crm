<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('invoices:mark-overdue')->daily()->withoutOverlapping();
Schedule::command('proposals:mark-expired')->daily()->withoutOverlapping();
Schedule::command('reminders:send')->dailyAt('08:00')->withoutOverlapping();
Schedule::command('invoices:generate-recurring')->dailyAt('07:30')->withoutOverlapping();
Schedule::command('expenses:generate-recurring')->dailyAt('07:35')->withoutOverlapping();
Schedule::command('backups:run')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('reports:send-scheduled')->dailyAt('07:00')->withoutOverlapping();
