<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function userStatistics(Request $request)
    {
        $selectedMonth = $request->input('month');
        $selectedYear = $request->input('year', date('Y'));

        if ($selectedMonth) {
            // Lấy số ngày trong tháng được chọn
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $selectedMonth, $selectedYear);

            // Lấy số lượng user đăng ký theo từng ngày trong tháng
            $usersData = User::whereYear('created_at', $selectedYear)
                ->whereMonth('created_at', $selectedMonth)
                ->selectRaw('DAY(created_at) as day, COUNT(*) as count')
                ->groupBy('day')
                ->pluck('count', 'day')
                ->toArray();

            // Tạo danh sách đầy đủ 1 -> số ngày trong tháng, nếu không có dữ liệu thì mặc định 0
            $labels = range(1, $daysInMonth); 
            $data = [];

            foreach ($labels as $day) {
                $data[] = $usersData[$day] ?? 0; // Nếu có dữ liệu thì lấy, không thì 0
            }

            $totalUsers = array_sum($data); // Tổng số user đăng ký trong tháng
        } else {
            // Nếu chỉ chọn năm → lấy số lượng user đăng ký theo từng tháng
            $usersData = User::whereYear('created_at', $selectedYear)
                ->selectRaw('MONTH(created_at) as month, COUNT(*) as count')
                ->groupBy('month')
                ->pluck('count', 'month')
                ->toArray();

            // Hiển thị 12 tháng
            $labels = array_map(function ($month) {
                return date("F", mktime(0, 0, 0, $month, 1));
            }, range(1, 12));

            $data = [];

            foreach (range(1, 12) as $month) {
                $data[] = $usersData[$month] ?? 0;
            }

            $totalUsers = array_sum($data); // Tổng số user đăng ký trong năm
        }

        return view('admin.index', compact('labels', 'data', 'selectedMonth', 'selectedYear', 'totalUsers'));
    }
}
