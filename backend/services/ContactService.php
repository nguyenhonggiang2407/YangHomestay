<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

/**
 * ═════════════════════════════════════════════════════════════════════════
 * CONTACT SERVICE — form liên hệ → contact_messages
 * Spam protection cơ bản: tối đa 3 tin / email trong 10 phút.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class ContactService
{
    private SupabaseClient $db;

    public function __construct()
    {
        $this->db = supabase();
    }

    public function submit(array $data): array
    {
        try {
            $fullName = Validation::string('full_name', $data['full_name'] ?? '', 100, true, 'họ tên');
            $email    = Validation::email('email', $data['email'] ?? '');
            $phone    = Validation::phoneVN('phone', $data['phone'] ?? '', false);
            $subject  = Validation::string('subject', $data['subject'] ?? '', 150, true, 'tiêu đề');
            $message  = Validation::string('message', $data['message'] ?? '', 3000, true, 'nội dung');
        } catch (ValidationException $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors, 'status' => 422];
        }

        // rate limit: 3 tin / email trong 10 phút
        $since = Pg::isoTime(time() - 600);
        $cnt = $this->db->select('contact_messages', [
            'select'  => 'id',
            'filters' => [Pg::eqStr('email', $email), 'created_at=gte.' . $since],
            'count'   => true,
        ]);
        if ($cnt['ok'] && $cnt['count'] !== null && $cnt['count'] >= 3) {
            return ['success' => false, 'message' => 'Bạn đã gửi quá nhiều tin nhắn, vui lòng thử lại sau.', 'status' => 429];
        }

        $ins = $this->db->insert('contact_messages', [
            'full_name' => $fullName,
            'email'     => $email,
            'phone'     => $phone !== '' ? $phone : null,
            'subject'   => $subject,
            'message'   => $message,
            'status'    => 'new',
            'created_at'=> gmdate('Y-m-d\TH:i:s\Z'),
        ]);

        if (!$ins['ok']) {
            Security::logError('contact', 'submit_failed', ['status' => $ins['status']]);
            return ['success' => false, 'message' => 'Lỗi khi gửi tin nhắn', 'status' => 503];
        }

        return ['success' => true, 'message' => 'Cảm ơn bạn! Chúng tôi sẽ phản hồi sớm nhất có thể.', 'status' => 201];
    }

    public function adminList(?string $status = null): array
    {
        $filters = [];
        $status = Pg::whitelist($status, ['new', 'read', 'replied', 'closed']);
        if ($status !== null) {
            $filters[] = Pg::eqStr('status', $status);
        }
        $res = $this->db->select('contact_messages', [
            'select'  => '*',
            'filters' => $filters,
            'order'   => 'created_at.desc',
            'limit'   => 200,
        ]);
        return ['success' => true, 'data' => $res['ok'] ? $res['data'] : [], 'status' => 200];
    }

    public function adminUpdateStatus(int $id, string $status): array
    {
        $status = Pg::whitelist($status, ['new', 'read', 'replied', 'closed']);
        if ($status === null) {
            return ['success' => false, 'message' => 'Trạng thái không hợp lệ.', 'status' => 422];
        }
        $res = $this->db->update('contact_messages', ['status' => $status], Pg::eqInt('id', $id));
        if (!$res['ok']) {
            return ['success' => false, 'message' => 'Lỗi cập nhật', 'status' => 503];
        }
        return ['success' => true, 'message' => 'Đã cập nhật', 'status' => 200];
    }
}
