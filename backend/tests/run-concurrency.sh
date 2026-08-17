#!/bin/bash
# ═════════════════════════════════════════════════════════════════════════
# CONCURRENCY TEST — 2 PHP process THẬT song song, cùng unit + cùng dates.
# Yêu cầu: đúng 1 SUCCESS (201) + 1 CONFLICT (409).
# ═════════════════════════════════════════════════════════════════════════
set -u
cd "$(dirname "$0")" || exit 2   # backend/tests/
cd .. || exit 2                  # backend/

# 1) Tạo customer test
CID=$(/c/xampp/php/php.exe -r '
require "bootstrap.php";
$a = new AuthService();
$e = "conc3." . time() . "@example.com";
$r = $a->register(["full_name" => "C3", "email" => $e, "phone" => "0901234567",
                   "password" => "ConcTest123!", "confirm_password" => "ConcTest123!"]);
echo (int)($r["data"]["id"] ?? 0);
' 2>/dev/null)
echo "customer=$CID"

# 2) Phóng 2 worker SONG SONG (cùng cwd backend/)
/c/xampp/php/php.exe tests/concurrency-test.php --worker "$CID" A > /tmp/concA.txt 2>&1 &
P1=$!
/c/xampp/php/php.exe tests/concurrency-test.php --worker "$CID" B > /tmp/concB.txt 2>&1 &
P2=$!
wait "$P1"; R1=$?
wait "$P2"; R2=$?

echo "--- A ---"; cat /tmp/concA.txt
echo "--- B ---"; cat /tmp/concB.txt

# 3) Dọn dữ liệu test
CONC_CID="$CID" /c/xampp/php/php.exe -r '
require "bootstrap.php";
$cid = (int)getenv("CONC_CID");
$db = supabase();
$db->delete("bookings", Pg::eqInt("customer_id", $cid));
$db->delete("customers", Pg::eqInt("id", $cid));
echo "cleaned customer $cid" . PHP_EOL;
' 2>&1 | grep -v Warning

# 4) Đánh giá
SA=$(grep -c "success=true" /tmp/concA.txt)
SB=$(grep -c "success=true" /tmp/concB.txt)
CA=$(grep -c "status=409" /tmp/concA.txt)
CB=$(grep -c "status=409" /tmp/concB.txt)
TOTAL_SUCCESS=$((SA + SB))
TOTAL_CONFLICT=$((CA + CB))
if [ "$TOTAL_SUCCESS" -eq 1 ] && [ "$TOTAL_CONFLICT" -eq 1 ]; then
  echo "RESULT: PASS (1 success + 1 conflict — advisory lock hoạt động)"
  exit 0
else
  echo "RESULT: FAIL (success=$TOTAL_SUCCESS conflict=$TOTAL_CONFLICT)"
  exit 1
fi
