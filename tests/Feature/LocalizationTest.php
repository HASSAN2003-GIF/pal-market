<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Finder\Finder;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use BuildsMarketplace, RefreshDatabase;

    public function test_api_messages_follow_the_requested_language(): void
    {
        $this->postJson('/api/login', ['login' => 'nobody@example.com', 'password' => 'wrong-password'], ['X-Locale' => 'sw'])
            ->assertUnprocessable()
            ->assertHeader('Content-Language', 'sw')
            ->assertJsonPath('errors.login.0', 'Barua pepe, namba ya simu au nenosiri si sahihi.');

        $this->postJson('/api/login', ['login' => 'nobody@example.com', 'password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertHeader('Content-Language', 'en')
            ->assertJsonPath('errors.login.0', 'The provided credentials are incorrect.');
    }

    public function test_validation_errors_are_translated_with_swahili_field_names(): void
    {
        $this->postJson('/api/register', [], ['Accept-Language' => 'sw-TZ,sw;q=0.9,en;q=0.5'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Tafadhali jaza barua pepe.')
            ->assertJsonPath('errors.password.0', 'Tafadhali jaza nenosiri.');
    }

    public function test_unsupported_languages_fall_back_to_english(): void
    {
        $this->postJson('/api/register', [], ['Accept-Language' => 'fr-FR,fr;q=0.9', 'X-Locale' => 'de'])
            ->assertUnprocessable()
            ->assertHeader('Content-Language', 'en')
            ->assertJsonPath('errors.email.0', 'The email field is required.');
    }

    public function test_saved_language_is_used_when_the_request_names_none(): void
    {
        $buyer = $this->makeBuyer();
        $buyer->forceFill(['locale' => 'sw'])->save();
        $token = $buyer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/me', ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'Tafadhali jaza jina.');
    }

    public function test_password_reset_email_is_sent_in_the_account_language(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'mteja@example.com']);
        $user->forceFill(['locale' => 'sw'])->save();

        $this->postJson('/api/forgot-password', ['email' => 'mteja@example.com'])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification, $channels, $notifiable, $locale) use ($user) {
            $this->assertSame('sw', $locale);

            app()->setLocale($locale);
            $mail = $notification->toMail($user);
            $this->assertSame('Badilisha nenosiri lako', $mail->subject);
            $this->assertSame('Badilisha nenosiri', $mail->actionText);
            $this->assertStringContainsString('/reset-password?token=', $mail->actionUrl);

            return true;
        });
    }

    public function test_every_backend_message_has_a_swahili_translation(): void
    {
        $translations = json_decode(file_get_contents(lang_path('sw.json')), true, flags: JSON_THROW_ON_ERROR);
        $missing = [];

        foreach (Finder::create()->files()->in(app_path())->name('*.php') as $file) {
            preg_match_all("/__\\('((?:[^'\\\\]|\\\\.)+)'/", $file->getContents(), $matches);
            foreach ($matches[1] as $key) {
                $key = stripslashes($key);
                // Keys such as validation.in live in lang/sw/*.php.
                if (! preg_match('/^[a-z_]+\.[a-z_.]+$/', $key) && ! array_key_exists($key, $translations)) {
                    $missing[] = $file->getRelativePathname().': '.$key;
                }
            }
        }

        $this->assertSame([], $missing, "Add these to lang/sw.json:\n".implode("\n", $missing));
    }

    public function test_swahili_translations_keep_their_placeholders(): void
    {
        $translations = json_decode(file_get_contents(lang_path('sw.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ($translations as $english => $swahili) {
            preg_match_all('/:[a-zA-Z]+/', $english, $expected);
            preg_match_all('/:[a-zA-Z]+/', $swahili, $actual);
            // A translation may drop a placeholder deliberately (see the :status message), never invent one.
            $this->assertEmpty(array_diff($actual[0], $expected[0]), "Unknown placeholder in: {$swahili}");
        }
    }
}
