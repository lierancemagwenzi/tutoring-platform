<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faqs = [
            // Both
            ['audience' => 'both', 'question' => 'What is ItsLearnable?', 'answer' => 'ItsLearnable is a tutoring marketplace and self-paced learning platform. Students can book one-on-one or group sessions with vetted tutors, or purchase self-paced courses to learn at their own pace.'],
            ['audience' => 'both', 'question' => 'How do I reset my password?', 'answer' => 'Click "Forgot password?" on the login page and enter your email address. We\'ll send you a 6-digit code to verify it\'s you, then you can set a new password.'],
            ['audience' => 'both', 'question' => 'How do I verify my email address?', 'answer' => 'After registering, we send a 6-digit verification code to your email. Enter it on the verification screen to activate your account. Didn\'t receive it? You can request a new code after 60 seconds.'],
            ['audience' => 'both', 'question' => 'What payment methods are accepted?', 'answer' => 'All payments are processed securely through PayFast, which supports major South African banks, credit and debit cards, and instant EFT.'],
            ['audience' => 'both', 'question' => 'How do I contact support?', 'answer' => 'Raise a support ticket from the Help section of your dashboard. Our team typically responds within one business day, and you can track the status and add replies right from the ticket.'],

            // Student
            ['audience' => 'student', 'question' => 'How do I book a session with a tutor?', 'answer' => 'Browse the Marketplace, filter by subject and grade, and open a tutor\'s profile to see their services and availability. Pick a time slot and submit a booking request — the tutor will accept or decline it.'],
            ['audience' => 'student', 'question' => 'When am I charged for a booking?', 'answer' => 'You\'re not charged when you request a booking. Once the tutor accepts, you\'ll be asked to pay to confirm the session — nothing is deducted before that.'],
            ['audience' => 'student', 'question' => 'Can I cancel a booking?', 'answer' => 'Yes, you can cancel a booking that hasn\'t been paid for yet from "My Bookings". Once a session is confirmed and paid, please contact the tutor directly or raise a support ticket to discuss rescheduling.'],
            ['audience' => 'student', 'question' => 'What happens if a tutor rejects my booking request?', 'answer' => 'You\'ll be notified immediately and won\'t be charged anything. You\'re free to request a different time slot or book with another tutor.'],
            ['audience' => 'student', 'question' => 'How do self-paced courses work?', 'answer' => 'Self-paced courses are pre-recorded, structured learning content you purchase once and complete on your own schedule. Each course is broken into modules with activities and assessments — complete each module in order to unlock the next.'],
            ['audience' => 'student', 'question' => 'Do I get a certificate when I finish a course?', 'answer' => 'Yes. Once you complete every required module and assessment in a self-paced course, a certificate is generated automatically and available for download from "My Certificates".'],
            ['audience' => 'student', 'question' => 'Can I get a refund on a course or booking?', 'answer' => 'Refund requests are reviewed case by case. Raise a support ticket explaining your situation and our team will get back to you with next steps.'],
            ['audience' => 'student', 'question' => 'How do I message my tutor?', 'answer' => 'Open any booking from "My Bookings" and use the chat on that page to message your tutor directly. They\'ll get an email and an in-app notification.'],

            // Tutor
            ['audience' => 'tutor', 'question' => 'How do I become an approved tutor?', 'answer' => 'After registering as a tutor, complete your profile, add the subjects and grades you want to teach, and submit your banking details. An admin will review your application — you\'ll be notified once you\'re approved.'],
            ['audience' => 'tutor', 'question' => 'Why do I need to add banking details before I\'m approved?', 'answer' => 'We require verified banking details upfront so payouts can be processed as soon as you start earning, with no delays once you\'re teaching.'],
            ['audience' => 'tutor', 'question' => 'How do I get paid?', 'answer' => 'Earnings from completed bookings and course sales appear in your Earnings page. Once a transaction is eligible for payout, an admin marks it as paid and the funds are transferred to your registered bank account.'],
            ['audience' => 'tutor', 'question' => 'What commission does the platform take?', 'answer' => 'The platform commission is shown on your Earnings page under "Your Commission Rate". It\'s deducted automatically from each transaction before your payout — you always see the net amount you\'ll receive.'],
            ['audience' => 'tutor', 'question' => 'How do I set my availability?', 'answer' => 'Go to Calendar in your teaching dashboard and add the dates and time slots you\'re available. Students can only book sessions during the windows you\'ve opened up.'],
            ['audience' => 'tutor', 'question' => 'How do I create a self-paced course?', 'answer' => 'From Self-Paced in your dashboard, click "New Course", add modules and activities (rich text, readings, assessments, etc.), then publish once you\'re happy with it. Published courses appear in the marketplace for students to purchase.'],
            ['audience' => 'tutor', 'question' => 'A subject request I submitted is still pending — what does that mean?', 'answer' => 'Every subject you want to teach is reviewed separately from your overall tutor approval. An admin checks it against your qualifications before approving it, so it may take a little longer than your account approval.'],
            ['audience' => 'tutor', 'question' => 'What if I have a dispute about a payment?', 'answer' => 'Open the transaction on your Earnings page and raise a payment ticket explaining the issue. An admin will investigate and update the ticket status as they work through it.'],
        ];

        foreach ($faqs as $position => $faq) {
            Faq::firstOrCreate(
                ['question' => $faq['question']],
                ['answer' => $faq['answer'], 'audience' => $faq['audience'], 'is_published' => true, 'position' => $position],
            );
        }
    }
}
