<div style="border-bottom: 3px solid #1B5E20; padding-bottom: 10px; margin-bottom: 18px;">
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="width: 50px; vertical-align: middle;">
                <div style="width: 40px; height: 40px; background: #1B5E20; border-radius: 8px; color: white;
                            text-align: center; line-height: 40px; font-weight: bold; font-size: 16px;">UV</div>
            </td>
            <td style="vertical-align: middle;">
                <div style="font-size: 18px; font-weight: bold; color: #1B5E20;">UparVital</div>
                <div style="font-size: 10px; color: #666;">{{ $reportTitle }}</div>
            </td>
            <td style="text-align: right; vertical-align: middle; font-size: 10px; color: #666;">
                Generado el {{ \Illuminate\Support\Carbon::now()->translatedFormat('d \d\e F \d\e Y, h:i A') }}
            </td>
        </tr>
    </table>
</div>
