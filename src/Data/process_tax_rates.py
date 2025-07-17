import os
import pandas as pd
import json

def create_json_from_csvs(data_folder='RawData', output_file='TaxRates-US.json'):
    """
    读取指定文件夹中的所有州税率CSV文件，并将它们合并成一个JSON文件。

    参数:
    data_folder (str): 存放CSV文件的文件夹名称。
    output_file (str): 输出的JSON文件名。
    """
    # 最终的JSON结构
    us_tax_rates = {
        "last_updated": "2025-07-16",
        "states": {}
    }

    # 风险等级的映射关系
    # 注意：您可以根据需要修改此映射。CSV中的数字将被替换为相应的文本。
    risk_level_map = {
        0: "L",
        1: "L",
        2: "M",
        3: "H",
        4: "VH"
    }

    print(f"开始处理 '{data_folder}' 文件夹中的文件...")

    # 检查Data文件夹是否存在
    if not os.path.isdir(data_folder):
        print(f"错误：找不到名为 '{data_folder}' 的文件夹。请确保该文件夹存在于脚本所在的目录中。")
        return

    # 获取所有CSV文件
    try:
        csv_files = [f for f in os.listdir(data_folder) if f.endswith('.csv') and f.startswith('TAXRATES_ZIP5_')]
        if not csv_files:
            print(f"错误：在 '{data_folder}' 文件夹中没有找到匹配的CSV文件。")
            return
    except FileNotFoundError:
        print(f"错误：文件夹 '{data_folder}' 不存在。")
        return


    # 遍历并处理每个CSV文件
    for file_name in csv_files:
        try:
            # 从文件名中提取州缩写 (例如 'TAXRATES_ZIP5_AK202507.csv' -> 'AK')
            state_abbr = file_name.split('_')[2][:2]
            print(f"正在处理 {state_abbr} 的数据...")

            file_path = os.path.join(data_folder, file_name)
            df = pd.read_csv(file_path)

            # 获取州的基本信息 (从第一行)
            # state_name = df.iloc[0]['State']
            # state_rate = str(df.iloc[0]['StateRate'])

            state_data = {}

            # 遍历DataFrame中的每一行来填充zip_codes
            # INSERT_YOUR_CODE
            # 这是因为在读取CSV文件时，pandas会自动将数字字符串转换为整数。
            # 如果邮政编码以零开头，整数表示将丢失这些前导零。
            # 为了避免这种情况，我们可以在读取CSV时指定dtype参数，将ZipCode列读取为字符串。
            df = pd.read_csv(file_path, dtype={'ZipCode': str})
            for index, row in df.iterrows():
                zip_code = str(row['ZipCode'])
                
                # 获取并转换风险等级
                risk_level_num = row.get('RiskLevel', -1) # 使用.get()以防该列不存在
                # risk_level_str = risk_level_map.get(risk_level_num, "Unknown")

                zip_data = {
                    "rgn": row['TaxRegionName'],
                    "cbr": str(row['EstimatedCombinedRate']),
                    "str": str(row['StateRate']),
                    "ctr": str(row['EstimatedCountyRate']),
                    "cir": str(row['EstimatedCityRate']),
                    "spr": str(row['EstimatedSpecialRate']),
                    "rsl": risk_level_num
                }
                state_data[zip_code] = zip_data
            
            # 将该州的数据添加到主字典中
            us_tax_rates["states"][row['State']] = state_data

        except Exception as e:
            print(f"处理文件 {file_name} 时出错: {e}")

    # 将最终的字典写入JSON文件
    try:
        with open(output_file, 'w', encoding='utf-8') as f:
            json.dump(us_tax_rates, f, indent=2, ensure_ascii=False)
        print(f"\n处理完成！数据已成功写入 '{output_file}'。")
    except Exception as e:
        print(f"写入JSON文件时出错: {e}")

# --- 运行脚本 ---
if __name__ == "__main__":
    create_json_from_csvs()